<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $r)
    {
        $debt = fn($q) => $q->where('status', 'confirmed')->where('payment_method', 'kredit');

        $rows = Supplier::query()
            ->withCount(['purchases as trx_count' => fn($q) => $q->where('status', 'confirmed')])
            ->withSum(['purchases as buy_total' => fn($q) => $q->where('status', 'confirmed')], 'total')
            ->withSum(['purchases as credit_total' => $debt], 'total')
            ->withSum(['purchases as credit_paid' => $debt], 'paid_amount')
            ->when($r->q, fn($q) => $q->where(fn($w) =>
                $w->where('name', 'like', "%{$r->q}%")->orWhere('code', 'like', "%{$r->q}%")->orWhere('phone', 'like', "%{$r->q}%")))
            ->when($r->debt === 'yes', fn($q) => $q->whereHas('purchases', fn($p) =>
                $p->where('status', 'confirmed')->where('payment_method', 'kredit')->whereColumn('paid_amount', '<', 'total')))
            ->when($r->debt === 'no', fn($q) => $q->whereDoesntHave('purchases', fn($p) =>
                $p->where('status', 'confirmed')->where('payment_method', 'kredit')->whereColumn('paid_amount', '<', 'total')))
            ->orderBy('code')->paginate(10)->withQueryString();

        $open = \App\Models\Purchase::with('supplier')->where('status', 'confirmed')->where('payment_method', 'kredit')
            ->whereColumn('paid_amount', '<', 'total')->get();
        $byS = $open->groupBy('supplier_id')->map(fn($g) => [
            'name' => $g->first()->supplier->name, 'left' => round((float) $g->sum(fn($p) => $p->total - $p->paid_amount), 2),
        ])->sortByDesc('left')->values();

        $sum = [
            'count'  => Supplier::count(),
            'debt'   => round((float) $byS->sum('left'), 2),
            'debtors'=> $byS->count(),
            'month'  => (float) \App\Models\Purchase::where('status', 'confirmed')
                ->whereDate('date', '>=', date('Y-m-01'))->whereDate('date', '<=', date('Y-m-d'))->sum('total'),
        ];

        return view('suppliers.index', compact('rows', 'sum', 'byS'));
    }

    public function create()
    {
        return view('suppliers.form', ['row' => null]);
    }

    public function store(Request $r)
    {
        Supplier::create($this->rules($r));
        return redirect()->route('suppliers.index')->with('success', 'Pemasok berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', ['row' => $supplier]);
    }

    public function update(Request $r, Supplier $supplier)
    {
        $supplier->update($this->rules($r, $supplier->id));
        return redirect()->route('suppliers.index')->with('success', 'Pemasok berhasil diubah.');
    }

    public function destroy(Supplier $supplier)
    {
        try {
            $supplier->delete();
            return back()->with('success', 'Pemasok dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'Pemasok tidak dapat dihapus karena sudah dipakai transaksi.');
        }
    }

    private function rules(Request $r, $id = null): array
    {
        return $r->validate([
            'code'    => 'required|max:20|unique:suppliers,code,' . $id,
            'name'    => 'required|max:255',
            'phone'   => 'nullable|max:30',
            'address' => 'nullable',
        ]);
    }
}