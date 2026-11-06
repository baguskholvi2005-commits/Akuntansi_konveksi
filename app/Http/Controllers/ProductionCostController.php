<?php

namespace App\Http\Controllers;

use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionCostController extends Controller
{
    private function withPaid()
    {
        return Production::with('item.unit')
            ->withSum(['payments as labor_paid' => fn($q) => $q->where('kind', 'labor')], 'amount')
            ->withSum(['payments as overhead_paid' => fn($q) => $q->where('kind', 'overhead')], 'amount');
    }

    public function index(Request $r)
    {
        $r->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'cat'  => 'nullable|in:labor,overhead', 'pay' => 'nullable|in:paid,debt',
            'q'    => 'nullable|max:100',
        ]);

        $from = $r->from ?: date('Y-m-01');
        $to   = $r->to ?: date('Y-m-d');
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        $prods = $this->withPaid()->where('status', 'selesai')
            ->whereDate('finished_date', '>=', $from)->whereDate('finished_date', '<=', $to)
            ->when($r->q, fn($q) => $q->where(fn($w) => $w->where('number', 'like', "%{$r->q}%")
                ->orWhereHas('item', fn($i) => $i->where('name', 'like', "%{$r->q}%"))))
            ->orderBy('finished_date')->orderBy('id')->get();

        // Total (tidak terpengaruh filter kategori dan status bayar)
        $t = [
            'material' => round((float) $prods->sum('material_cost'), 2),
            'labor'    => round((float) $prods->sum('labor_cost'), 2),
            'overhead' => round((float) $prods->sum('overhead_cost'), 2),
            'total'    => round((float) $prods->sum('total_cost'), 2),
            'qty'      => round((float) $prods->sum('qty'), 2),
            'count'    => $prods->count(),
        ];
        $t['avg'] = $t['qty'] > 0 ? round($t['total'] / $t['qty'], 2) : 0;

        // Satu baris per komponen biaya
        $entries = collect();
        foreach ($prods as $p) {
            foreach (['labor' => 'Tenaga kerja', 'overhead' => 'Overhead'] as $k => $label) {
                $amount = (float) $p->{$k . '_cost'};
                if ($amount <= 0) {
                    continue;
                }
                $left = max(round((float) $p->{$k . '_payable'} - (float) $p->{$k . '_paid'}, 2), 0);
                $entries->push((object) [
                    'p' => $p, 'kind' => $k, 'label' => $label, 'amount' => $amount,
                    'rate' => $p->qty > 0 ? $amount / $p->qty : 0, 'left' => $left,
                ]);
            }
        }
        $entries = $entries
            ->when($r->cat, fn($c) => $c->where('kind', $r->cat))
            ->when($r->pay === 'debt', fn($c) => $c->filter(fn($e) => $e->left > 0))
            ->when($r->pay === 'paid', fn($c) => $c->filter(fn($e) => $e->left <= 0))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = new LengthAwarePaginator($entries->forPage($page, 15)->values(), $entries->count(), 15, $page,
            ['path' => $r->url(), 'query' => $r->query()]);

        // Jurnal biaya tenaga kerja dan overhead pada periode
        $journal = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('j.source_type', (new Production)->getMorphClass())
            ->whereIn('j.event', ['labor', 'overhead'])
            ->whereDate('j.date', '>=', $from)->whereDate('j.date', '<=', $to)
            ->groupBy('a.code', 'a.name')
            ->selectRaw('a.code, a.name, SUM(jl.debit) AS d, SUM(jl.credit) AS c')
            ->orderBy('a.code')->get();
        $wipDebit  = (float) ($journal->firstWhere('code', '1302')->d ?? 0);
        $journalOk = abs($wipDebit - ($t['labor'] + $t['overhead'])) < 0.01;

        // Biaya per produk
        $byProduct = $prods->groupBy('item_id')->map(function ($g) {
            $qty = (float) $g->sum('qty'); $tot = (float) $g->sum('total_cost');
            return ['name' => $g->first()->item->name, 'batches' => $g->count(), 'qty' => $qty,
                    'total' => $tot, 'unit' => $qty > 0 ? $tot / $qty : 0];
        })->sortByDesc('total')->values();

        // Utang produksi saat ini (semua periode)
        $open = $this->withPaid()->where('status', 'selesai')->get();
        $debt = [
            'labor'    => round((float) $open->sum(fn($p) => max($p->labor_payable - $p->labor_paid, 0)), 2),
            'overhead' => round((float) $open->sum(fn($p) => max($p->overhead_payable - $p->overhead_paid, 0)), 2),
        ];
        $paidInPeriod = round((float) DB::table('production_payments')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('amount'), 2);

        return view('reports.production', compact('from', 'to', 't', 'rows', 'journal', 'journalOk', 'byProduct', 'debt', 'paidInPeriod'));
    }
}