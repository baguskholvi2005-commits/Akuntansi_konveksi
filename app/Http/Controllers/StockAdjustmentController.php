<?php

namespace App\Http\Controllers;

use App\Models\{Item, Journal, StockAdjustment};
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function index(Request $r)
    {
        $rows = StockAdjustment::with('item.unit')
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->when($r->from,   fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,     fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->q,      fn($q) => $q->where('number', 'like', "%{$r->q}%"))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('adjustments.index', compact('rows'));
    }

    public function create()
    {
        return view('adjustments.create', [
            'items' => Item::with('unit')->orderBy('type')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $r, StockAdjustmentService $svc)
    {
        $data = $r->validate([
            'date'        => 'required|date|before_or_equal:today',
            'item_id'     => 'required|exists:items,id',
            'counted_qty' => 'required|numeric|min:0',
            'reason'      => 'required|max:200',
        ]);

        $a = $svc->create($data);

        return redirect()->route('stock-adjustments.show', $a)
            ->with('success', 'Draft penyesuaian disimpan. Menunggu persetujuan admin atau keuangan.');
    }

    public function show(StockAdjustment $adjustment)
    {
        $adjustment->load(['item.unit', 'creator', 'approver']);

        $journals = Journal::with('lines.account')
            ->where(['source_type' => $adjustment->getMorphClass(), 'source_id' => $adjustment->id])
            ->orderBy('id')->get();

        return view('adjustments.show', ['a' => $adjustment, 'journals' => $journals]);
    }

    public function approve(Request $r, StockAdjustment $adjustment, StockAdjustmentService $svc)
    {
        $data = $r->validate(['unit_cost' => 'nullable|numeric|gt:0']);

        try {
            $svc->approve($adjustment, isset($data['unit_cost']) ? (float) $data['unit_cost'] : null);
            return back()->with('success', 'Penyesuaian disetujui. Stok dan jurnal sudah diperbarui.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'draft') {
            return back()->with('error', 'Penyesuaian yang sudah disetujui tidak boleh dihapus.');
        }
        $adjustment->delete();

        return redirect()->route('stock-adjustments.index')->with('success', 'Draft penyesuaian dihapus.');
    }
}