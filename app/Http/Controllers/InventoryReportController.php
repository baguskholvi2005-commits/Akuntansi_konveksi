<?php

namespace App\Http\Controllers;

use App\Models\{Item, InventoryMovement};
use App\Services\InventoryReportService;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function card(Request $r, InventoryReportService $svc)
    {
        $items = Item::with(['unit', 'category'])->orderBy('type')->orderBy('code')->get();
        $item = $data = $recon = $chart = null;

        if ($r->filled('item_id')) {
            $r->validate([
                'item_id' => 'exists:items,id',
                'from'    => 'nullable|date',
                'to'      => 'nullable|date',
            ]);

            if ($r->from && $r->to && $r->to < $r->from) {
                return back()->withInput()->with('error', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
            }

            $item = Item::with(['unit', 'category'])->findOrFail($r->item_id);
            $data = $svc->card($item, $r->from, $r->to);

            // Saldo kartu terakhir harus sama dengan stok di master barang
            $last    = InventoryMovement::where('item_id', $item->id)->orderByDesc('id')->first();
            $cardQty = $last ? (float) $last->balance_qty : 0.0;
            $recon   = [
                'ok'    => abs($cardQty - (float) $item->stock_qty) < 0.005,
                'card'  => $cardQty,
                'stock' => (float) $item->stock_qty,
            ];

            $labels = [];
            $qty    = [];
            if ($r->from) {
                $labels[] = 'Awal';
                $qty[]    = $data['opening']['qty'];
            }
            foreach ($data['rows'] as $m) {
                $labels[] = \Carbon\Carbon::parse($m->date)->format('d/m') . ' ' . $m->reference;
                $qty[]    = (float) $m->balance_qty;
            }
            $chart = ['labels' => $labels, 'qty' => $qty, 'min' => (float) $item->min_stock];
        }

        return view('inventory.card', compact('items', 'item', 'data', 'recon', 'chart'));
    }

    public function report(InventoryReportService $svc)
    {
        $data = $svc->report();

        return view('inventory.report', compact('data'));
    }
}