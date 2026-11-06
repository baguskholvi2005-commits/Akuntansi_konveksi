<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\ReportService;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $r, ReportService $rep)
    {
        $r->validate(['account_id' => 'nullable|exists:accounts,id', 'from' => 'nullable|date', 'to' => 'nullable|date']);

        // Tanpa pilihan, buka akun Kas lebih dulu
        if (!$r->filled('account_id')) {
            $def = Account::where('code', '1101')->first();
            if ($def) {
                return redirect()->route('ledger.index', ['account_id' => $def->id]);
            }
        }

        if ($r->from && $r->to && $r->to < $r->from) {
            return back()->withInput()->with('error', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        $accounts = Account::orderBy('code')->get();
        $acc = $data = $recon = null;
        $side = collect();

        if ($r->filled('account_id')) {
            $acc  = Account::findOrFail($r->account_id);
            $data = $rep->ledger($acc, $r->from, $r->to);

            $tb  = $rep->trialBalance($r->to ?: date('Y-m-d'));
            $ids = Account::pluck('id', 'code');

            $row   = collect($tb['rows'])->firstWhere('code', $acc->code);
            $tbBal = $row ? ($acc->normal_balance === 'debit' ? $row['sd'] - $row['sk'] : $row['sk'] - $row['sd']) : 0;
            $recon = ['tb' => round($tbBal, 2), 'ok' => abs($tbBal - $data['closing']) < 0.01, 'balanced' => $tb['balanced']];

            $side = collect($tb['rows'])->filter(fn($x) => max($x['sd'], $x['sk']) > 0)->map(fn($x) => [
                'id' => $ids[$x['code']], 'code' => $x['code'], 'name' => $x['name'],
                'amount' => max($x['sd'], $x['sk']), 'dc' => $x['sd'] > 0 ? 'D' : 'K',
            ])->values();
        }

        return view('ledger.index', compact('accounts', 'acc', 'data', 'recon', 'side'));
    }
}