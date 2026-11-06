<?php

namespace App\Http\Controllers;

use App\Models\OwnerTransaction;
use App\Services\{OwnerService, LedgerService};
use Illuminate\Http\Request;

class OwnerTransactionController extends Controller
{
    public function index(Request $r)
    {
        $rows = OwnerTransaction::query()
            ->when($r->from, fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,   fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->type, fn($q) => $q->where('type', $r->type))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('equity.index', compact('rows'));
    }

    public function create(LedgerService $ledger)
    {
        return view('equity.create', [
            'saldoKas'  => $ledger->balance('1101'),
            'saldoBank' => $ledger->balance('1102'),
        ]);
    }

    public function store(Request $r, OwnerService $svc)
    {
        $data = $r->validate([
            'date'         => 'required|date|before_or_equal:today',
            'type'         => 'required|in:modal,prive',
            'amount'       => 'required|numeric|gt:0',
            'account_code' => 'required|in:1101,1102',
            'notes'        => 'nullable|max:255',
        ]);

        try {
            $svc->create($data);
            return redirect()->route('equity.index')->with('success', 'Transaksi pemilik berhasil dicatat dan dijurnal.');
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function void(Request $r, OwnerTransaction $tx, OwnerService $svc)
    {
        $data = $r->validate(['reason' => 'required|max:200']);

        try {
            $svc->void($tx, $data['reason']);
            return back()->with('success', 'Transaksi dibatalkan dengan jurnal pembalik.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}