<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\{ExpenseService, LedgerService};
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $r)
    {
        $query = Expense::query()
            ->when($r->from,     fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,       fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->category, fn($q) => $q->where('category', $r->category))
            ->when($r->q,        fn($q) => $q->where(fn($w) =>
                $w->where('number', 'like', "%{$r->q}%")->orWhere('description', 'like', "%{$r->q}%")));

        $total = (clone $query)->where('status', 'posted')->sum('amount');
        $rows  = $query->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('expenses.index', compact('rows', 'total'));
    }

    public function create(LedgerService $ledger)
    {
        return view('expenses.create', [
            'saldoKas'  => $ledger->balance('1101'),
            'saldoBank' => $ledger->balance('1102'),
        ]);
    }

    public function store(Request $r, ExpenseService $svc)
    {
        $data = $r->validate([
            'date'            => 'required|date|before_or_equal:today',
            'category'        => 'required|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description'     => 'required|max:255',
            'amount'          => 'required|numeric|gt:0',
            'payment_account' => 'required|in:1101,1102',
            'proof'           => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        try {
            $svc->create($data, $r->file('proof'));
            return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil dicatat dan dijurnal.');
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function void(Request $r, Expense $expense, ExpenseService $svc)
    {
        $data = $r->validate(['reason' => 'required|max:200']);

        try {
            $svc->void($expense, $data['reason']);
            return back()->with('success', 'Pengeluaran dibatalkan dengan jurnal pembalik.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}