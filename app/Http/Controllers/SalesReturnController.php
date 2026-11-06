<?php

namespace App\Http\Controllers;

use App\Models\{Journal, SalesOrder, SalesReturn};
use App\Services\{LedgerService, SalesReturnService};
use Illuminate\Http\Request;

class SalesReturnController extends Controller
{
    public function index(Request $r)
    {
        $rows = SalesReturn::with('order.customer')
            ->when($r->from, fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,   fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->q,    fn($q) => $q->where('number', 'like', "%{$r->q}%"))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('sales_returns.index', compact('rows'));
    }

    public function create(Request $r, SalesReturnService $svc, LedgerService $ledger)
    {
        $orders = SalesOrder::with('customer')->where('status', 'terkirim')
            ->orderByDesc('delivered_date')->get();

        $order = null;
        $lines = [];
        if ($r->filled('order_id')) {
            $order = SalesOrder::with(['customer', 'items.item.unit'])
                ->where('status', 'terkirim')->findOrFail($r->order_id);
            $lines = $svc->returnable($order);
        }

        return view('sales_returns.create', [
            'orders' => $orders, 'order' => $order, 'lines' => $lines,
            'saldoKas' => $ledger->balance('1101'), 'saldoBank' => $ledger->balance('1102'),
        ]);
    }

    public function store(Request $r, SalesReturnService $svc)
    {
        $data = $r->validate([
            'order_id'          => 'required|exists:sales_orders,id',
            'date'              => 'required|date|before_or_equal:today',
            'items'             => 'required|array',
            'items.*.item_id'   => 'required|exists:items,id',
            'items.*.qty'       => 'nullable|numeric|min:0',
            'refund_account'    => 'nullable|in:1101,1102',
            'notes'             => 'nullable|max:255',
        ]);

        try {
            $ret = $svc->create($data);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales-returns.show', $ret)->with('success', 'Retur berhasil dicatat. Stok dan jurnal sudah dibuat.');
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load(['order.customer', 'items.item.unit']);

        $journals = Journal::with('lines.account')
            ->where(['source_type' => $salesReturn->getMorphClass(), 'source_id' => $salesReturn->id])
            ->orderBy('id')->get();

        return view('sales_returns.show', ['ret' => $salesReturn, 'journals' => $journals]);
    }
}