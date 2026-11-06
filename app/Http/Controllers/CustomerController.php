<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $r)
    {
        $unpaid = fn($q) => $q->where('status', 'terkirim')->whereRaw('(paid_amount + receivable_cut) < total');

        $rows = Customer::query()
            ->withCount('orders')
            ->withSum(['orders as sold_total' => fn($q) => $q->where('status', 'terkirim')], 'total')
            ->withSum(['orders as recv_total' => fn($q) => $q->where('status', 'terkirim')], 'total')
            ->withSum(['orders as recv_paid' => fn($q) => $q->where('status', 'terkirim')], 'paid_amount')
            ->withSum(['orders as recv_cut' => fn($q) => $q->where('status', 'terkirim')], 'receivable_cut')
            ->withCount(['orders as active_count' => fn($q) => $q->where('status', '!=', 'terkirim')])
            ->when($r->q, fn($q) => $q->where(fn($w) =>
                $w->where('name', 'like', "%{$r->q}%")->orWhere('code', 'like', "%{$r->q}%")->orWhere('phone', 'like', "%{$r->q}%")))
            ->when($r->recv === 'yes', fn($q) => $q->whereHas('orders', $unpaid))
            ->when($r->recv === 'no', fn($q) => $q->whereDoesntHave('orders', $unpaid))
            ->orderBy('code')->paginate(10)->withQueryString();

        $open = \App\Models\SalesOrder::where('status', 'terkirim')->whereRaw('(paid_amount + receivable_cut) < total')->get();
        $today = \Carbon\Carbon::today();
        $aging = ['0-15' => 0, '16-30' => 0, '>30' => 0];
        foreach ($open as $o) {
            $left = $o->total - $o->paid_amount - $o->receivable_cut;
            $age  = (int) \Carbon\Carbon::parse($o->delivered_date)->startOfDay()->diffInDays($today);
            $aging[$age <= 15 ? '0-15' : ($age <= 30 ? '16-30' : '>30')] += $left;
        }

        $delivered = \App\Models\SalesOrder::where('status', 'terkirim');
        $sum = [
            'count'  => Customer::count(),
            'recv'   => round((float) $open->sum(fn($o) => $o->total - $o->paid_amount - $o->receivable_cut), 2),
            'recv_n' => $open->count(),
            'avg'    => (float) ((clone $delivered)->count() ? (clone $delivered)->avg('total') : 0),
            'active' => \App\Models\SalesOrder::where('status', '!=', 'terkirim')->distinct('customer_id')->count('customer_id'),
        ];

        return view('customers.index', compact('rows', 'sum', 'aging'));
    }

    public function create()
    {
        return view('Customers.form', ['row' => null]);
    }

    public function store(Request $r)
    {
        Customer::create($this->rules($r));
        return redirect()->route('Customers.index')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Customer $Customer)
    {
        return view('Customers.form', ['row' => $Customer]);
    }

    public function update(Request $r, Customer $Customer)
    {
        $Customer->update($this->rules($r, $Customer->id));
        return redirect()->route('Customers.index')->with('success', 'Pelanggan berhasil diubah.');
    }

    public function destroy(Customer $Customer)
    {
        try {
            $Customer->delete();
            return back()->with('success', 'Pelanggan dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'Pelanggan tidak dapat dihapus karena sudah dipakai transaksi.');
        }
    }

    private function rules(Request $r, $id = null): array
    {
        return $r->validate([
            'code'    => 'required|max:20|unique:Customers,code,' . $id,
            'name'    => 'required|max:255',
            'phone'   => 'nullable|max:30',
            'address' => 'nullable',
        ]);
    }
}