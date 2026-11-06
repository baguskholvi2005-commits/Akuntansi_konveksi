<?php

namespace App\Services;

use App\Models\{InventoryMovement, SalesOrder, SalesReturn};
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public function __construct(
        private InventoryService $inv,
        private JournalService $journal,
        private LedgerService $ledger
    ) {}

    /** Per barang: jumlah terjual, sudah diretur, dan sisa yang masih boleh diretur. $o->items.item harus sudah dimuat. */
    public function returnable(SalesOrder $o): array
    {
        $returned = DB::table('sales_return_items as i')
            ->join('sales_returns as r', 'r.id', '=', 'i.sales_return_id')
            ->where('r.sales_order_id', $o->id)
            ->groupBy('i.item_id')
            ->selectRaw('i.item_id, SUM(i.qty) AS q')
            ->pluck('q', 'item_id');

        $lines = [];
        foreach ($o->items->groupBy('item_id') as $itemId => $g) {
            $sold  = round((float) $g->sum('qty'), 2);
            $value = round((float) $g->sum('subtotal'), 2);
            $done  = round((float) ($returned[$itemId] ?? 0), 2);

            $lines[$itemId] = [
                'item'      => $g->first()->item,
                'sold'      => $sold,
                'returned'  => $done,
                'remaining' => round($sold - $done, 2),
                'price'     => $sold > 0 ? round($value / $sold, 2) : 0,
            ];
        }

        return $lines;
    }

    /** Harga pokok per unit saat barang ini dijual, dari kartu persediaan. */
    private function originalCost(SalesOrder $o, int $itemId): float
    {
        $m = InventoryMovement::where('source_type', $o->getMorphClass())
            ->where('source_id', $o->id)
            ->where('item_id', $itemId)
            ->where('out_qty', '>', 0)
            ->selectRaw('SUM(out_value) AS v, SUM(out_qty) AS q')
            ->first();

        return ($m && $m->q > 0) ? (float) $m->v / (float) $m->q : 0.0;
    }

    public function create(array $d): SalesReturn
    {
        return DB::transaction(function () use ($d) {
            $o = SalesOrder::with(['items.item', 'customer'])->lockForUpdate()->findOrFail($d['order_id']);

            if ($o->status !== 'terkirim') {
                throw new \DomainException('Retur hanya untuk pesanan yang sudah terkirim.');
            }
            if ($d['date'] < $o->delivered_date) {
                throw new \DomainException('Tanggal retur tidak boleh lebih awal dari tanggal kirim.');
            }

            $avail  = $this->returnable($o);
            $picked = [];

            foreach ($d['items'] as $row) {
                $qty = round((float) ($row['qty'] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                $id = (int) $row['item_id'];
                if (!isset($avail[$id])) {
                    throw new \DomainException('Ada barang yang tidak termasuk dalam pesanan ini.');
                }
                $picked[$id] = round(($picked[$id] ?? 0) + $qty, 2);
            }

            if (!$picked) {
                throw new \DomainException('Isi jumlah retur minimal untuk satu barang.');
            }

            $total = 0;
            foreach ($picked as $id => $qty) {
                if ($qty > $avail[$id]['remaining'] + 0.001) {
                    throw new \DomainException(
                        "Jumlah retur {$avail[$id]['item']->name} melebihi sisa yang bisa diretur ({$avail[$id]['remaining']})."
                    );
                }
                $total += round($qty * $avail[$id]['price'], 2);
            }
            $total = round($total, 2);

            // Potong piutang lebih dulu; sisanya dikembalikan sebagai uang
            $outstanding = round($o->total - $o->paid_amount - $o->receivable_cut, 2);
            $cut    = round(min($total, max($outstanding, 0)), 2);
            $refund = round($total - $cut, 2);
            $acc    = $d['refund_account'] ?? null;

            if ($refund > 0) {
                if (!$acc) {
                    throw new \DomainException(
                        'Pelanggan sudah membayar, jadi Rp ' . number_format($refund, 0, ',', '.')
                        . ' harus dikembalikan. Pilih akun pengembalian uang (Kas atau Bank).'
                    );
                }
                $saldo = $this->ledger->balance($acc);
                if ($refund > $saldo) {
                    throw new \DomainException(
                        $this->ledger->name($acc) . ' tidak cukup untuk mengembalikan uang. Saldo: Rp ' . number_format($saldo, 0, ',', '.')
                    );
                }
            }

            $ret = SalesReturn::create([
                'number'              => DocNumber::make('RTJ', 'sales_returns'),
                'date'                => $d['date'],
                'sales_order_id'      => $o->id,
                'total'               => $total,
                'receivable_cut'      => $cut,
                'refund_amount'       => $refund,
                'refund_account_code' => $refund > 0 ? $acc : null,
                'notes'               => $d['notes'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            // Barang masuk lagi ke stok pada harga pokok asli
            $cogs = 0;
            foreach ($picked as $id => $qty) {
                $cost  = $this->originalCost($o, $id);
                $value = $this->inv->in(
                    $id, $qty, $cost, $d['date'], $ret->number,
                    "Retur penjualan dari {$o->customer->name}", $ret
                );
                $ret->items()->create([
                    'item_id' => $id, 'qty' => $qty,
                    'unit_price' => $avail[$id]['price'],
                    'subtotal'   => round($qty * $avail[$id]['price'], 2),
                    'unit_cost'  => $cost, 'cost_value' => $value,
                ]);
                $cogs += $value;
            }
            $cogs = round($cogs, 2);

            $lines = [['account' => '4102', 'debit' => $total]];
            if ($cut > 0)    $lines[] = ['account' => '1201', 'credit' => $cut];
            if ($refund > 0) $lines[] = ['account' => $acc,   'credit' => $refund];

            $this->journal->post($d['date'], "Retur penjualan {$ret->number} ({$o->number})", $lines, $ret, 'return');

            if ($cogs > 0) {
                $this->journal->post($d['date'], "Pengembalian HPP retur {$ret->number}", [
                    ['account' => '1303', 'debit'  => $cogs],
                    ['account' => '5101', 'credit' => $cogs],
                ], $ret, 'cogs');
            }

            $ret->update(['cogs' => $cogs]);
            $o->update(['receivable_cut' => round($o->receivable_cut + $cut, 2)]);

            return $ret;
        });
    }
}