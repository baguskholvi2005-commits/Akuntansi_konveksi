<?php

namespace App\Services;

use App\Models\{Item, InventoryMovement, Production};

class InventoryReportService
{
    public function __construct(private LedgerService $ledger) {}

    /** Kartu persediaan satu barang. */
    public function card(Item $item, ?string $from, ?string $to): array
    {
        // Saldo awal = saldo setelah mutasi terakhir sebelum tanggal awal
        $opening = ['qty' => 0, 'cost' => 0, 'value' => 0];
        if ($from) {
            $last = InventoryMovement::where('item_id', $item->id)
                ->whereDate('date', '<', $from)
                ->orderByDesc('id')->first();
            if ($last) {
                $opening = [
                    'qty'   => (float) $last->balance_qty,
                    'cost'  => (float) $last->balance_cost,
                    'value' => (float) $last->balance_value,
                ];
            }
        }

        // Urutan mengikuti id, karena saldo dihitung berurutan saat transaksi disimpan
        $rows = InventoryMovement::where('item_id', $item->id)
            ->when($from, fn($q) => $q->whereDate('date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('date', '<=', $to))
            ->orderBy('id')->get();

        $closing = $rows->isNotEmpty()
            ? [
                'qty'   => (float) $rows->last()->balance_qty,
                'cost'  => (float) $rows->last()->balance_cost,
                'value' => (float) $rows->last()->balance_value,
            ]
            : $opening;

        return [
            'opening' => $opening,
            'rows'    => $rows,
            'total'   => [
                'in_qty'    => round($rows->sum('in_qty'), 2),
                'in_value'  => round($rows->sum('in_value'), 2),
                'out_qty'   => round($rows->sum('out_qty'), 2),
                'out_value' => round($rows->sum('out_value'), 2),
            ],
            'closing' => $closing,
        ];
    }

    /** Laporan stok semua barang + pencocokan dengan jurnal. */
    public function report(): array
    {
        $items = Item::with('unit')->orderBy('code')->get()->map(function ($i) {
            $qty = (float) $i->stock_qty;
            $min = (float) $i->min_stock;

            if ($qty <= 0)                    $status = 'habis';
            elseif ($min > 0 && $qty <= $min) $status = 'menipis';
            else                              $status = 'aman';

            return (object) [
                'code'   => $i->code,
                'name'   => $i->name,
                'type'   => $i->type,
                'unit'   => $i->unit->name,
                'qty'    => $qty,
                'min'    => $min,
                'cost'   => (float) $i->avg_cost,
                'value'  => round($qty * (float) $i->avg_cost, 2),
                'status' => $status,
            ];
        });

        $raw = $items->where('type', 'raw_material');
        $fg  = $items->where('type', 'product');

        $wipProductions = Production::with('item')->where('status', 'proses')
            ->orderBy('date')->get();

        $rawValue = round($raw->sum('value'), 2);
        $fgValue  = round($fg->sum('value'), 2);
        $wipValue = round((float) $wipProductions->sum('material_cost'), 2);

        $check = function (string $label, string $acc, float $stock) {
            $ledger = $this->ledger->balance($acc);
            $diff   = round($stock - $ledger, 2);
            return [
                'label'  => $label,
                'acc'    => $acc,
                'stock'  => $stock,
                'ledger' => $ledger,
                'diff'   => $diff,
                'ok'     => abs($diff) < 1,   // selisih pembulatan di bawah Rp 1 dianggap cocok
            ];
        };

        return [
            'raw'            => $raw,
            'fg'             => $fg,
            'wip'            => $wipProductions,
            'raw_value'      => $rawValue,
            'fg_value'       => $fgValue,
            'wip_value'      => $wipValue,
            'total_value'    => round($rawValue + $fgValue + $wipValue, 2),
            'low_count'      => $items->whereIn('status', ['habis', 'menipis'])->count(),
            'reconciliation' => [
                $check('Bahan baku', '1301', $rawValue),
                $check('Barang dalam proses', '1302', $wipValue),
                $check('Produk jadi', '1303', $fgValue),
            ],
        ];
    }
}