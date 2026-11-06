<?php

namespace App\Services;

use App\Models\{Purchase, Production, SalesOrder};
use Carbon\Carbon;

class DashboardService
{
    private const MONTHS = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    public function __construct(
        private FinancialReportService $fin,
        private InventoryReportService $inv,
        private LedgerService $ledger
    ) {}

    /** Bagian operasional: boleh dilihat semua peran. */
    public function operational(): array
    {
        $stock  = $this->inv->report();
        $lowRaw = $stock['raw']->whereIn('status', ['menipis', 'habis'])->values();

        $recent = SalesOrder::with('customer')->withSum('items as qty_sum', 'qty')
            ->orderByDesc('id')->limit(5)->get();

        return [
            'inventory_total' => $stock['total_value'],
            'raw_value'       => $stock['raw_value'],
            'wip_value'       => $stock['wip_value'],
            'fg_value'        => $stock['fg_value'],
            'low_raw'         => $lowRaw,
            'wip_count'       => Production::where('status', 'proses')->count(),
            'pending_orders'  => SalesOrder::where('status', '!=', 'terkirim')->count(),
            'recent_orders'   => $recent,
        ];
    }

    private function figures(string $from, string $to): array
    {
        $inc = $this->fin->incomeStatement($from, $to);
        $pur = Purchase::where('status', 'confirmed')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('total');

        return [
            'sales'     => $inc['revenue_total'],
            'hpp'       => $inc['hpp_total'],
            'gross'     => $inc['gross'],
            'expenses'  => $inc['expense_total'],
            'net'       => $inc['net'],
            'purchases' => round((float) $pur, 2),
        ];
    }

    /** Bagian keuangan untuk satu periode, dibandingkan dengan periode sebelumnya yang sama panjang. */
    public function financial(string $from, string $to): array
    {
        $cur = $this->figures($from, $to);

        $days     = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo   = Carbon::parse($from)->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $prev     = $this->figures($prevFrom->toDateString(), $prevTo->toDateString());

        $pct = fn($c, $p) => abs($p) < 0.005 ? null : round(($c - $p) / abs($p) * 100, 1);

        $prod = Production::where('status', 'selesai')
            ->whereDate('finished_date', '>=', $from)->whereDate('finished_date', '<=', $to);

        $cashOnly = $this->ledger->balance('1101');
        $bankOnly = $this->ledger->balance('1102');

        return $cur + [
            'prod_cost'  => round((float) (clone $prod)->sum('total_cost'), 2),
            'prod_count' => (clone $prod)->count(),
            'cash'       => round($cashOnly + $bankOnly, 2),
            'cash_only'  => $cashOnly,
            'bank_only'  => $bankOnly,
            'payables'   => $this->ledger->balance('2101'),
            'receivables'=> $this->ledger->balance('1201'),
            'payable_count' => Purchase::where('status', 'confirmed')->where('payment_method', 'kredit')
                ->whereColumn('paid_amount', '<', 'total')->count(),
            'receivable_count' => SalesOrder::where('status', 'terkirim')
                ->whereRaw('(paid_amount + receivable_cut) < total')->count(),
            'prev_from'  => $prevFrom->toDateString(),
            'prev_to'    => $prevTo->toDateString(),
            'change'     => [
                'sales'     => $pct($cur['sales'], $prev['sales']),
                'purchases' => $pct($cur['purchases'], $prev['purchases']),
                'expenses'  => $pct($cur['expenses'], $prev['expenses']),
                'net'       => $pct($cur['net'], $prev['net']),
            ],
        ];
    }

    /** Data grafik: 6 bulan, berakhir di bulan dari tanggal $to. */
    public function monthly(string $to): array
    {
        $last = Carbon::parse($to)->startOfMonth();
        $labels = $sales = $purchases = $expenses = [];

        for ($i = 5; $i >= 0; $i--) {
            $m = $last->copy()->subMonths($i);
            $s = $m->toDateString();
            $e = $m->copy()->endOfMonth()->toDateString();

            $inc = $this->fin->incomeStatement($s, $e);

            $labels[]    = self::MONTHS[$m->month - 1] . ' ' . $m->year;
            $sales[]     = $inc['revenue_total'];
            $expenses[]  = $inc['expense_total'];
            $purchases[] = round((float) Purchase::where('status', 'confirmed')
                ->whereDate('date', '>=', $s)->whereDate('date', '<=', $e)->sum('total'), 2);
        }

        return compact('labels', 'sales', 'purchases', 'expenses');
    }
}