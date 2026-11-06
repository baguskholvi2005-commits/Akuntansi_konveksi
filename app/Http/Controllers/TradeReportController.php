<?php

namespace App\Http\Controllers;

use App\Services\TradeReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TradeReportController extends Controller
{
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

    public function purchases(Request $r, TradeReportService $svc)
    {
        [$from, $to] = $this->period($r);
        $data = $svc->purchases($from, $to);

        return view('reports.purchases', compact('from', 'to', 'data'));
    }

    public function sales(Request $r, TradeReportService $svc)
    {
        [$from, $to] = $this->period($r);
        $data = $svc->sales($from, $to);

        return view('reports.sales', compact('from', 'to', 'data'));
    }

    public function debts(TradeReportService $svc)
    {
        $data = $svc->debts();

        return view('reports.debts', compact('data'));
    }
}