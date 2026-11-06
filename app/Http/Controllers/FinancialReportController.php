<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    /** Membaca periode dari URL. Dari-tanggal boleh kosong (= sejak awal). */
    private function period(Request $r): array
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $from = $r->from ?: null;
        $to   = $r->to ?: date('Y-m-d');

        if ($from && $to < $from) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        return [$from, $to];
    }

    public function income(Request $r, FinancialReportService $svc)
    {
        [$from, $to] = $this->period($r);
        $data = $svc->incomeStatement($from, $to);

        return view('reports.income', compact('from', 'to', 'data'));
    }

        /** Gabungkan akun periode ini dan pembanding berdasarkan kode akun. */
    private function merge(array $cur, ?array $prev): array
    {
        $rows = [];
        foreach ($cur as $a) {
            $rows[$a['code']] = ['code' => $a['code'], 'name' => $a['name'], 'cur' => $a['amount'], 'prev' => 0.0];
        }
        foreach ($prev ?? [] as $a) {
            $rows[$a['code']] ??= ['code' => $a['code'], 'name' => $a['name'], 'cur' => 0.0, 'prev' => 0.0];
            $rows[$a['code']]['prev'] = $a['amount'];
        }
        ksort($rows);

        return array_values($rows);
    }

    public function balance(Request $r, FinancialReportService $svc)
    {
        $r->validate(['as_of' => 'nullable|date']);

        $asOf    = $r->as_of ?: date('Y-m-d');
        $data    = $svc->balanceSheet($asOf);
        // Perbandingan aktif secara bawaan; dimatikan bila form dikirim tanpa centang
        $compare = $r->has('as_of') ? $r->boolean('compare') : true;

        $prevDate = $prev = null;
        if ($compare) {
            $prevDate = Carbon::parse($asOf)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $prev     = $svc->balanceSheet($prevDate);
        }

        $sec = [
            'assets'      => $this->merge($data['assets'], $prev['assets'] ?? null),
            'liabilities' => $this->merge($data['liabilities'], $prev['liabilities'] ?? null),
            'equity'      => $this->merge($data['equity'], $prev['equity'] ?? null),
        ];

        return view('reports.balance', compact('asOf', 'data', 'compare', 'prevDate', 'prev', 'sec'));
    }

    public function cashflow(Request $r, FinancialReportService $svc)
    {
        [$from, $to] = $this->period($r);
        $data = $svc->cashFlow($from, $to);

        // Saldo Kas dan Bank per akhir periode, menurut jurnal
        $bal = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.code', ['1101', '1102'])->whereDate('j.date', '<=', $to)
            ->groupBy('a.code')->selectRaw('a.code AS code, SUM(jl.debit - jl.credit) AS s')->pluck('s', 'code');

        return view('reports.cashflow', compact('from', 'to', 'data', 'bal'));
    }

    public function equity(Request $r, FinancialReportService $svc)
    {
        [$from, $to] = $this->period($r);
        $data = $svc->equityChange($from, $to);

        return view('reports.equity', compact('from', 'to', 'data'));
    }
}