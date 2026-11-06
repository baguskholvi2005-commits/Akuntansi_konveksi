<?php

namespace App\Http\Controllers;

use App\Models\{SalesOrder, SalesPayment, Customer, Item, Journal};
use App\Services\{SalesService, DocNumber};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class SalesOrderController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $base = SalesOrder::query()
            ->when($r->from, fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,   fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->when($r->pay === 'unpaid', fn($q) => $q->where('paid_amount', 0))
            ->when($r->pay === 'partial', fn($q) => $q->where('paid_amount', '>', 0)->whereRaw('(paid_amount + receivable_cut) < total'))
            ->when($r->pay === 'paid', fn($q) => $q->whereRaw('(paid_amount + receivable_cut) >= total'))
            ->when($r->q, fn($q) => $q->where(fn($w) => $w->where('number', 'like', "%{$r->q}%")
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$r->q}%"))));

        $rows = $base->with(['customer', 'items.item'])
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        $recv = SalesOrder::where('status', 'terkirim')->whereRaw('(paid_amount + receivable_cut) < total')
            ->selectRaw('COALESCE(SUM(total - paid_amount - receivable_cut),0) s, COUNT(*) c')->first();

        $stats = [
            'month_total' => (float) SalesOrder::whereDate('date', '>=', date('Y-m-01'))->whereDate('date', '<=', date('Y-m-d'))->sum('total'),
            'month_count' => SalesOrder::whereDate('date', '>=', date('Y-m-01'))->count(),
            'cash_in'     => (float) \App\Models\SalesPayment::whereDate('date', '>=', date('Y-m-01'))->whereDate('date', '<=', date('Y-m-d'))->sum('amount'),
            'recv'        => round((float) $recv->s, 2),
            'recv_count'  => (int) $recv->c,
            'active'      => SalesOrder::where('status', '!=', 'terkirim')->count(),
        ];

        // Pratinjau: pesanan dipilih lewat ?pick=ID, atau yang pertama di halaman
        $pick = null;
        if ($r->filled('pick')) {
            $pick = SalesOrder::with(['customer', 'items.item.unit'])->find($r->pick);
        }
        $pick = $pick ?: ($rows->first() ? SalesOrder::with(['customer', 'items.item.unit'])->find($rows->first()->id) : null);

        $pickJournals = $pick
            ? Journal::with('lines.account')->where(['source_type' => $pick->getMorphClass(), 'source_id' => $pick->id])->orderBy('id')->get()
            : collect();

        return view('orders.index', compact('rows', 'stats', 'pick', 'pickJournals'));
    }

    public function create()
    {
        return view('orders.create', [
            'customers' => Customer::orderBy('name')->get(),
            'products'  => Item::with('unit')->where('type', 'product')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'date'               => 'required|date',
            'customer_id'        => 'required|exists:customers,id',
            'payment_type'       => 'required|in:tunai,kredit',
            'notes'              => 'nullable|max:500',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => ['required', Rule::exists('items', 'id')->where('type', 'product')],
            'items.*.qty'        => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|gt:0',
        ]);

        $order = DB::transaction(function () use ($data) {
            $o = SalesOrder::create([
                'number'       => DocNumber::make('SO', 'sales_orders'),
                'date'         => $data['date'],
                'customer_id'  => $data['customer_id'],
                'payment_type' => $data['payment_type'],
                'notes'        => $data['notes'] ?? null,
                'created_by'   => Auth::id(),
            ]);

            $total = 0;
            foreach ($data['items'] as $row) {
                $sub = round($row['qty'] * $row['unit_price'], 2);
                $o->items()->create([
                    'item_id' => $row['item_id'], 'qty' => $row['qty'],
                    'unit_price' => $row['unit_price'], 'subtotal' => $sub,
                ]);
                $total += $sub;
            }
            $o->update(['total' => $total]);

            return $o;
        });

        return redirect()->route('orders.show', $order)->with('success', 'Pesanan berhasil dibuat.');
    }

    public function show(SalesOrder $order)
    {
        $order->load(['customer', 'items.item.unit', 'payments']);

        $journals = Journal::with('lines.account')
            ->where(function ($q) use ($order) {
                $q->where(['source_type' => $order->getMorphClass(), 'source_id' => $order->id])
                  ->orWhere(function ($w) use ($order) {
                      $w->where('source_type', (new SalesPayment)->getMorphClass())
                        ->whereIn('source_id', $order->payments->pluck('id'));
                  });
            })->orderBy('id')->get();

        return view('orders.show', compact('order', 'journals'));
    }

    public function progress(Request $r, SalesOrder $order, SalesService $svc)
    {
        $data = $r->validate(['to' => 'required|in:dalam_produksi,selesai']);

        try {
            $svc->moveStatus($order, $data['to']);
            return back()->with('success', 'Status pesanan diperbarui.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pay(Request $r, SalesOrder $order, SalesService $svc)
    {
        $data = $r->validate([
            'date'         => 'required|date',
            'amount'       => 'required|numeric|gt:0',
            'account_code' => 'required|in:1101,1102',
        ]);

        try {
            $svc->pay($order, $data['date'], (float) $data['amount'], $data['account_code']);
            return back()->with('success', 'Pembayaran berhasil dicatat.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function deliver(Request $r, SalesOrder $order, SalesService $svc)
    {
        $data = $r->validate(['date' => 'required|date']);

        try {
            $svc->deliver($order, $data['date']);
            return back()->with('success', 'Barang terkirim. Pendapatan, HPP, dan stok sudah dicatat.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(SalesOrder $order)
    {
        if ($order->status === 'terkirim' || $order->paid_amount > 0) {
            return back()->with('error', 'Pesanan yang sudah menerima pembayaran atau sudah terkirim tidak boleh dihapus.');
        }
        $order->delete();

        return redirect()->route('orders.index')->with('success', 'Pesanan dihapus.');
    }
}