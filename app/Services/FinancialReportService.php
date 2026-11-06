<?php

namespace App\Services;

use App\Models\{Account, Journal};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /** Total debit dan kredit per akun pada rentang tanggal (null = tanpa batas). */
    private function sums(?string $from, ?string $to)
    {
        return DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->when($from, fn($q) => $q->whereDate('j.date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('j.date', '<=', $to))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->selectRaw('a.code, a.name, a.type, SUM(jl.debit) AS d, SUM(jl.credit) AS c')
            ->orderBy('a.code')
            ->get();
    }

    private function dayBefore(string $date): string
    {
        return Carbon::parse($date)->subDay()->toDateString();
    }

    /** Laba bersih = kredit - debit pada akun pendapatan, hpp, dan beban. */
    private function profit($rows): float
    {
        $p = 0.0;
        foreach ($rows as $r) {
            if (in_array($r->type, ['pendapatan', 'hpp', 'beban'])) {
                $p += (float) $r->c - (float) $r->d;
            }
        }
        return round($p, 2);
    }

    /** Total ekuitas = modal - prive + laba (semua akun ekuitas bersaldo kredit positif). */
    private function equityTotal($rows): float
    {
        $e = 0.0;
        foreach ($rows as $r) {
            if ($r->type === 'ekuitas') {
                $e += (float) $r->c - (float) $r->d;
            }
        }
        return round($e + $this->profit($rows), 2);
    }

    // ------------------------------------------------------------------
    // LABA RUGI
    // ------------------------------------------------------------------
    public function incomeStatement(?string $from, string $to): array
    {
        $rev = $hpp = $exp = [];

        foreach ($this->sums($from, $to) as $r) {
            $d = (float) $r->d;
            $c = (float) $r->c;

            if ($r->type === 'pendapatan') {
                // Retur penjualan (saldo debit) otomatis menjadi angka negatif
                $rev[] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($c - $d, 2)];
            } elseif ($r->type === 'hpp') {
                $hpp[] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($d - $c, 2)];
            } elseif ($r->type === 'beban') {
                $exp[] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($d - $c, 2)];
            }
        }

        $revTotal = round(collect($rev)->sum('amount'), 2);
        $hppTotal = round(collect($hpp)->sum('amount'), 2);
        $expTotal = round(collect($exp)->sum('amount'), 2);
        $gross    = round($revTotal - $hppTotal, 2);

        return [
            'revenue' => $rev, 'revenue_total' => $revTotal,
            'hpp' => $hpp, 'hpp_total' => $hppTotal,
            'gross' => $gross,
            'expenses' => $exp, 'expense_total' => $expTotal,
            'net' => round($gross - $expTotal, 2),
        ];
    }

    // ------------------------------------------------------------------
    // NERACA
    // ------------------------------------------------------------------
    public function balanceSheet(string $asOf): array
    {
        $rows = $this->sums(null, $asOf);   // kumulatif sejak awal

        $assets = $liab = $equity = [];
        foreach ($rows as $r) {
            $d = (float) $r->d;
            $c = (float) $r->c;

            if ($r->type === 'aset') {
                $assets[] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($d - $c, 2)];
            } elseif ($r->type === 'liabilitas') {
                $liab[] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($c - $d, 2)];
            } elseif ($r->type === 'ekuitas') {
                // Prive (saldo debit) otomatis bernilai negatif
                $equity[] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($c - $d, 2)];
            }
        }

        $profit      = $this->profit($rows);
        $assetTotal  = round(collect($assets)->sum('amount'), 2);
        $liabTotal   = round(collect($liab)->sum('amount'), 2);
        $equityTotal = round(collect($equity)->sum('amount') + $profit, 2);

        return [
            'assets' => $assets, 'asset_total' => $assetTotal,
            'liabilities' => $liab, 'liability_total' => $liabTotal,
            'equity' => $equity, 'profit' => $profit, 'equity_total' => $equityTotal,
            'liab_equity_total' => round($liabTotal + $equityTotal, 2),
            'balanced' => abs($assetTotal - ($liabTotal + $equityTotal)) < 0.01,
        ];
    }

    // ------------------------------------------------------------------
    // ARUS KAS (metode langsung)
    // ------------------------------------------------------------------
    public function cashFlow(?string $from, string $to): array
    {
        $cashIds = Account::where('is_cash', true)->pluck('id')->all();

        $cashBalance = function (?string $start, string $end) use ($cashIds) {
            $o = DB::table('journal_lines as jl')
                ->join('journals as j', 'j.id', '=', 'jl.journal_id')
                ->whereIn('jl.account_id', $cashIds)
                ->when($start, fn($q) => $q->whereDate('j.date', '>=', $start))
                ->whereDate('j.date', '<=', $end)
                ->selectRaw('COALESCE(SUM(jl.debit),0) AS d, COALESCE(SUM(jl.credit),0) AS c')
                ->first();
            return round((float) $o->d - (float) $o->c, 2);
        };

        $opening = $from ? $cashBalance(null, $this->dayBefore($from)) : 0.0;

        $journals = Journal::with('lines.account')
            ->when($from, fn($q) => $q->whereDate('date', '>=', $from))
            ->whereDate('date', '<=', $to)
            ->get();

        $groups = ['operasi' => [], 'pendanaan' => []];
        $inflow = $outflow = 0.0;

        foreach ($journals as $j) {
            $cash = 0.0;
            $equityName = null;

            foreach ($j->lines as $l) {
                if ($l->account->is_cash) {
                    $cash += (float) $l->debit - (float) $l->credit;
                } elseif (in_array($l->account->code, ['3101', '3102'])) {
                    $equityName = $l->account->name;
                }
            }

            $cash = round($cash, 2);
            if (abs($cash) < 0.005) {
                continue;   // tidak ada pergerakan kas (atau pindah Kas <-> Bank)
            }

            if ($cash > 0) { $inflow += $cash; } else { $outflow += -$cash; }

            // Lawan transaksi modal/prive = pendanaan, selain itu operasi
            $section = $equityName ? 'pendanaan' : 'operasi';
            $label   = $equityName ?? ($j->is_reversal ? 'Pembalikan jurnal' : $j->source_label);

            $groups[$section][$label] = ($groups[$section][$label] ?? 0) + $cash;
        }

        $toRows = function (array $g) {
            ksort($g);
            $rows = [];
            foreach ($g as $label => $amount) {
                $rows[] = ['label' => $label, 'amount' => round($amount, 2)];
            }
            return $rows;
        };

        $op  = $toRows($groups['operasi']);
        $fin = $toRows($groups['pendanaan']);

        $opTotal  = round(collect($op)->sum('amount'), 2);
        $finTotal = round(collect($fin)->sum('amount'), 2);
        $net      = round($opTotal + $finTotal, 2);
        $closing  = round($opening + $net, 2);
        $actual   = $cashBalance(null, $to);

        return [
            'operating' => $op, 'operating_total' => $opTotal,
            'investing_total' => 0.0,
            'financing' => $fin, 'financing_total' => $finTotal,
            'net' => $net, 'opening' => $opening, 'closing' => $closing,
            'inflow' => round($inflow, 2), 'outflow' => round($outflow, 2),
            'actual' => $actual,
            'ok' => abs($closing - $actual) < 0.01,
        ];
    }

    // ------------------------------------------------------------------
    // PERUBAHAN MODAL
    // ------------------------------------------------------------------
    public function equityChange(?string $from, string $to): array
    {
        $opRows = $from ? $this->sums(null, $this->dayBefore($from)) : collect();
        $opCap = $opPrive = 0.0;
        foreach ($opRows as $r) {
            if ($r->code === '3101') $opCap   += (float) $r->c - (float) $r->d;
            if ($r->code === '3102') $opPrive += (float) $r->d - (float) $r->c;
        }
        $opProfit = $this->profit($opRows);
        $opening  = round($opCap - $opPrive + $opProfit, 2);

        $period  = $this->sums($from, $to);
        $capital = 0.0;
        $prive   = 0.0;
        foreach ($period as $r) {
            if ($r->code === '3101') $capital += (float) $r->c - (float) $r->d;
            if ($r->code === '3102') $prive   += (float) $r->d - (float) $r->c;
        }

        $profit   = $this->profit($period);
        $closing  = round($opening + $capital + $profit - $prive, 2);
        $expected = $this->equityTotal($this->sums(null, $to));

        return [
            'opening' => $opening,
            'opening_capital' => round($opCap, 2), 'opening_prive' => round($opPrive, 2), 'opening_profit' => $opProfit,
            'capital' => round($capital, 2),
            'profit'  => $profit,
            'prive'   => round($prive, 2),
            'closing' => $closing,
            'ok'      => abs($closing - $expected) < 0.01,
        ];
    }
}