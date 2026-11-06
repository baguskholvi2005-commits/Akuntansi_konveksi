<?php

namespace App\Services;

use App\Models\{Item, StockAdjustment};
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        private InventoryService $inv,
        private JournalService $journal
    ) {}

    /** Draft: hanya mencatat hasil hitung fisik. Stok dan jurnal BELUM berubah. */
    public function create(array $d): StockAdjustment
    {
        return StockAdjustment::create([
            'number'      => DocNumber::make('ADJ', 'stock_adjustments'),
            'date'        => $d['date'],
            'item_id'     => $d['item_id'],
            'counted_qty' => round((float) $d['counted_qty'], 2),
            'reason'      => $d['reason'],
            'created_by'  => auth()->id(),
        ]);
    }

    /** Persetujuan: selisih dihitung terhadap stok SAAT INI, lalu stok dan jurnal diubah. */
    public function approve(StockAdjustment $adjustment, ?float $unitCost = null): void
    {
        DB::transaction(function () use ($adjustment, $unitCost) {
            $a = StockAdjustment::lockForUpdate()->findOrFail($adjustment->id);

            if ($a->status !== 'draft') {
                throw new \DomainException('Penyesuaian ini sudah diproses.');
            }

            $item  = Item::lockForUpdate()->findOrFail($a->item_id);
            $before = round((float) $item->stock_qty, 2);
            $diff   = round((float) $a->counted_qty - $before, 2);

            if (abs($diff) < 0.005) {
                throw new \DomainException('Hasil hitung sama dengan stok sistem, tidak ada yang perlu disesuaikan. Hapus draft ini.');
            }

            $invAcc = $item->type === 'raw_material' ? '1301' : '1303';
            $desc   = "Penyesuaian stok {$item->name}: {$a->reason}";

            if ($diff < 0) {
                // Stok fisik kurang: barang keluar pada harga rata-rata
                $cost  = (float) $item->avg_cost;
                $value = $this->inv->out($item->id, -$diff, $a->date, $a->number, $desc, $a);
                if ($value <= 0) {
                    throw new \DomainException('Nilai selisih nol (barang ini belum punya harga rata-rata).');
                }
                $this->journal->post($a->date, "Penyesuaian stok {$a->number}: kekurangan {$item->name}", [
                    ['account' => '6109', 'debit'  => $value],
                    ['account' => $invAcc, 'credit' => $value],
                ], $a, 'post');
            } else {
                // Stok fisik lebih: barang masuk pada harga rata-rata (atau harga yang diisi bila belum ada)
                $cost = (float) $item->avg_cost > 0 ? (float) $item->avg_cost : (float) $unitCost;
                if ($cost <= 0) {
                    throw new \DomainException('Barang ini belum punya harga rata-rata. Isi harga satuan saat menyetujui.');
                }
                $value = $this->inv->in($item->id, $diff, $cost, $a->date, $a->number, $desc, $a);
                $this->journal->post($a->date, "Penyesuaian stok {$a->number}: kelebihan {$item->name}", [
                    ['account' => $invAcc, 'debit'  => $value],
                    ['account' => '6109',  'credit' => $value],
                ], $a, 'post');
            }

            $a->update([
                'status'      => 'posted',
                'system_qty'  => $before,
                'diff_qty'    => $diff,
                'unit_cost'   => $cost,
                'value'       => $value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
    }
}