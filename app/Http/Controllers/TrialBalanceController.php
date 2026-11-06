<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;

class TrialBalanceController extends Controller
{
    public function index(Request $r, ReportService $rep)
    {
        $r->validate(['from' => 'nullable|date', 'as_of' => 'nullable|date']);

        $asOf = $r->as_of ?: date('Y-m-d');
        $from = $r->from ?: null;
        if ($from && $asOf < $from) {
            return back()->withInput()->with('error', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        $data = $rep->trialPeriod($from, $asOf);
        $rows = collect($data['rows'])
            ->when($r->q, fn($c) => $c->filter(fn($x) => stripos($x['code'] . ' ' . $x['name'], $r->q) !== false))
            ->when(!$r->boolean('zero'), fn($c) => $c->filter(fn($x) => max($x['cd'], $x['ck']) > 0 || $x['md'] > 0 || $x['mk'] > 0))
            ->values();

        $byType = collect($data['rows'])->groupBy('type')->map(fn($g) => round($g->sum(fn($x) => $x['cd'] - $x['ck']), 2));

        return view('trial.index', compact('asOf', 'from', 'data', 'rows', 'byType'));
    }
}