<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** Neraca saldo kumulatif sampai tanggal $asOf. */
    public function trialBalance(string $asOf): array
    {
        $sums = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->whereDate('j.date', '<=', $asOf)
            ->groupBy('jl.account_id')
            ->selectRaw('jl.account_id, SUM(jl.debit) AS d, SUM(jl.credit) AS c')
            ->get()->keyBy('account_id');

        $rows = [];
        $tot  = ['debit' => 0, 'credit' => 0, 'sd' => 0, 'sk' => 0];

        foreach (Account::orderBy('code')->get() as $a) {
            $s = $sums->get($a->id);
            if (!$s) {
                continue;               // akun tanpa transaksi tidak ditampilkan
            }

            $d   = round((float) $s->d, 2);
            $c   = round((float) $s->c, 2);
            $net = round($d - $c, 2);

            $row = [
                'code'   => $a->code,
                'name'   => $a->name,
                'debit'  => $d,
                'credit' => $c,
                'sd'     => $net > 0 ? $net : 0,     // saldo di sisi debit
                'sk'     => $net < 0 ? -$net : 0,    // saldo di sisi kredit
            ];
            $rows[] = $row;

            $tot['debit']  += $d;
            $tot['credit'] += $c;
            $tot['sd']     += $row['sd'];
            $tot['sk']     += $row['sk'];
        }

        foreach ($tot as $k => $v) {
            $tot[$k] = round($v, 2);
        }

        return [
            'rows'     => $rows,
            'total'    => $tot,
            'balanced' => abs($tot['sd'] - $tot['sk']) < 0.01 && abs($tot['debit'] - $tot['credit']) < 0.01,
        ];
    }

        /** Neraca saldo berperiode: saldo awal, mutasi, saldo akhir per akun. */
    public function trialPeriod(?string $from, string $to): array
    {
        $agg = function (?string $a, ?string $b) {
            return DB::table('journal_lines as jl')->join('journals as j', 'j.id', '=', 'jl.journal_id')
                ->when($a, fn($q) => $q->whereDate('j.date', '>=', $a))
                ->when($b, fn($q) => $q->whereDate('j.date', '<=', $b))
                ->groupBy('jl.account_id')->selectRaw('jl.account_id, SUM(jl.debit) d, SUM(jl.credit) c')->get()->keyBy('account_id');
        };

        $before = $from ? $agg(null, \Carbon\Carbon::parse($from)->subDay()->toDateString()) : collect();
        $during = $agg($from, $to);

        $rows = [];
        $t = ['od' => 0, 'ok' => 0, 'md' => 0, 'mk' => 0, 'cd' => 0, 'ck' => 0];

        foreach (Account::orderBy('code')->get() as $a) {
            $b = $before->get($a->id); $m = $during->get($a->id);
            if (!$b && !$m) continue;

            $open = round((float) ($b->d ?? 0) - (float) ($b->c ?? 0), 2);
            $md = round((float) ($m->d ?? 0), 2); $mk = round((float) ($m->c ?? 0), 2);
            $close = round($open + $md - $mk, 2);

            $r = ['code' => $a->code, 'name' => $a->name, 'type' => $a->type,
                  'od' => max($open, 0), 'ok' => max(-$open, 0), 'md' => $md, 'mk' => $mk,
                  'cd' => max($close, 0), 'ck' => max(-$close, 0)];
            $rows[] = $r;
            foreach ($t as $k => $_) { $t[$k] += $r[$k]; }
        }
        foreach ($t as $k => $v) { $t[$k] = round($v, 2); }

        return ['rows' => $rows, 'total' => $t,
                'balanced' => abs($t['cd'] - $t['ck']) < 0.01 && abs($t['md'] - $t['mk']) < 0.01 && abs($t['od'] - $t['ok']) < 0.01];
    }

    /** Buku besar satu akun: saldo awal, mutasi, saldo berjalan. */
    public function ledger(Account $acc, ?string $from, ?string $to): array
    {
        $sign = $acc->normal_balance === 'debit' ? 1 : -1;

        $opening = 0.0;
        if ($from) {
            $o = DB::table('journal_lines as jl')
                ->join('journals as j', 'j.id', '=', 'jl.journal_id')
                ->where('jl.account_id', $acc->id)
                ->whereDate('j.date', '<', $from)
                ->selectRaw('COALESCE(SUM(jl.debit),0) AS d, COALESCE(SUM(jl.credit),0) AS c')
                ->first();
            $opening = round(($o->d - $o->c) * $sign, 2);
        }

        $lines = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->where('jl.account_id', $acc->id)
            ->when($from, fn($q) => $q->whereDate('j.date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('j.date', '<=', $to))
            ->orderBy('j.date')->orderBy('j.id')->orderBy('jl.id')
            ->select('j.id as journal_id', 'j.date', 'j.number', 'j.description', 'j.source_type', 'jl.memo', 'jl.debit', 'jl.credit')
            ->get();

        // Lawan akun: akun lain pada jurnal yang sama
        $others = DB::table('journal_lines as jl')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('jl.journal_id', $lines->pluck('journal_id')->unique()->all())
            ->where('jl.account_id', '!=', $acc->id)
            ->select('jl.journal_id', 'a.code', 'a.name')->get()->groupBy('journal_id');

        $running = $opening;
        $td = 0;
        $tc = 0;
        $rows = [];

        foreach ($lines as $l) {
            $running = round($running + ($l->debit - $l->credit) * $sign, 2);
            $td += $l->debit;
            $tc += $l->credit;

            $ctr = collect($others[$l->journal_id] ?? [])->unique('code');
            $counter = $ctr->take(2)->map(fn($x) => $x->code . ' ' . $x->name)->implode(', ')
                     . ($ctr->count() > 2 ? ' dan ' . ($ctr->count() - 2) . ' akun lain' : '');

            $rows[] = [
                'journal_id'  => $l->journal_id,
                'date'        => $l->date,
                'number'      => $l->number,
                'description' => $l->description . ($l->memo ? ' (' . $l->memo . ')' : ''),
                'source_type' => $l->source_type,
                'counter'     => $counter,
                'debit'       => (float) $l->debit,
                'credit'      => (float) $l->credit,
                'balance'     => $running,
            ];
        }

        return [
            'opening'      => $opening,
            'rows'         => $rows,
            'total_debit'  => round($td, 2),
            'total_credit' => round($tc, 2),
            'closing'      => $running,
        ];
    }
}