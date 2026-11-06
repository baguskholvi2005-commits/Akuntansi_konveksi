<?php

namespace App\Services;

use App\Models\{Production, Purchase, PurchasePayment, SalesOrder, SalesPayment, SalesReturn, InventoryMovement};
use Carbon\Carbon;

class TradeReportService
{
    public function __construct(
        private LedgerService $ledger,
        private FinancialReportService $fin
    ) {}

    // ------------------------------------------------------------------
    // PEMBELIAN
    // ------------------------------------------------------------------
    public function purchases(?string $from, string $to): array
    {
        $rows = Purchase::with('supplier')
            ->where('status', 'confirmed')
            ->when($from, fn($q) => $q->whereDate('date', '>=', $from))
            ->whereDate('date', '<=', $to)
            ->orderBy('date')->orderBy('id')->get();

        $bySupplier = $rows->groupBy('supplier_id')->map(function ($g) {
            $total = round((float) $g->sum('total'), 2);
            $paid  = round((float) $g->sum('paid_amount'), 2);
            return [
                'name'        => $g->first()->supplier->name,
                'count'       => $g->count(),
                'total'       => $total,
                'paid'        => $paid,
                'outstanding' => round($total - $paid, 2),
            ];
        })->sortByDesc('total')->values();

        $cash   = round((float) $rows->where('payment_method', 'tunai')->sum('total'), 2);
        $credit = round((float) $rows->where('payment_method', 'kredit')->sum('total'), 2);

        $debtPayments = round((float) PurchasePayment::query()
            ->when($from, fn($q) => $q->whereDate('date', '>=', $from))
            ->whereDate('date', '<=', $to)
            ->sum('amount'), 2);

        return [
            'rows'          => $rows,
            'by_supplier'   => $bySupplier,
            'count'         => $rows->count(),
            'total'         => round($cash + $credit, 2),
            'cash'          => $cash,
            'credit'        => $credit,
            'debt_payments' => $debtPayments,
            'cash_out'      => round($cash + $debtPayments, 2),   // uang yang benar-benar keluar
        ];
    }

    // ------------------------------------------------------------------
    // PENJUALAN
    // ------------------------------------------------------------------
    public function sales(?string $from, string $to): array
    {
        $orders = SalesOrder::with('customer')
            ->where('status', 'terkirim')
            ->when($from, fn($q) => $q->whereDate('delivered_date', '>=', $from))
            ->whereDate('delivered_date', '<=', $to)
            ->orderBy('delivered_date')->orderBy('id')->get();

        // HPP per pesanan diambil dari kartu persediaan (barang keluar karena penjualan)
        $cogs = InventoryMovement::where('source_type', (new SalesOrder)->getMorphClass())
            ->whereIn('source_id', $orders->pluck('id'))
            ->groupBy('source_id')
            ->selectRaw('source_id, SUM(out_value) AS hpp')
            ->pluck('hpp', 'source_id');

        $rows = $orders->map(function ($o) use ($cogs) {
            $hpp = round((float) ($cogs[$o->id] ?? 0), 2);
            return (object) [
                'order'  => $o,
                'hpp'    => $hpp,
                'profit' => round((float) $o->total - $hpp, 2),
            ];
        });

        $byCustomer = $rows->groupBy(fn($r) => $r->order->customer_id)->map(function ($g) {
            $total = round((float) $g->sum(fn($r) => $r->order->total), 2);
            $hpp   = round((float) $g->sum('hpp'), 2);
            return [
                'name'   => $g->first()->order->customer->name,
                'count'  => $g->count(),
                'total'  => $total,
                'hpp'    => $hpp,
                'profit' => round($total - $hpp, 2),
            ];
        })->sortByDesc('total')->values();

        $total = round((float) $rows->sum(fn($r) => $r->order->total), 2);
        $hpp   = round((float) $rows->sum('hpp'), 2);

        // Pembayaran yang diterima pada periode (DP maupun pelunasan)
        $pay = SalesPayment::query()
            ->when($from, fn($q) => $q->whereDate('date', '>=', $from))
            ->whereDate('date', '<=', $to)
            ->groupBy('type')->selectRaw('type, SUM(amount) AS s')->pluck('s', 'type');
        $dp   = round((float) ($pay['dp'] ?? 0), 2);
        $lunas = round((float) ($pay['pelunasan'] ?? 0), 2);

        // Pesanan yang belum terkirim (posisi saat ini, bukan per periode)
        $pending = SalesOrder::where('status', '!=', 'terkirim');

        // Retur pada periode (mengurangi penjualan dan HPP)
        $ret = SalesReturn::query()
            ->when($from, fn($q) => $q->whereDate('date', '>=', $from))
            ->whereDate('date', '<=', $to);
        $retTotal = round((float) (clone $ret)->sum('total'), 2);
        $retCogs  = round((float) (clone $ret)->sum('cogs'), 2);

        // Pencocokan dengan Laba Rugi pada periode yang sama
        $inc = $this->fin->incomeStatement($from, $to);

        return [
            'rows'        => $rows,
            'by_customer' => $byCustomer,
            'count'       => $rows->count(),
            'total'       => $total,
            'hpp'         => $hpp,
            'profit'      => round($total - $hpp, 2),
            'dp'          => $dp,
            'settlement'  => $lunas,
            'received'    => round($dp + $lunas, 2),
            'pending_count' => (clone $pending)->count(),
            'pending_total' => round((float) (clone $pending)->sum('total'), 2),
            'pending_paid'  => round((float) (clone $pending)->sum('paid_amount'), 2),
            'income_revenue' => $inc['revenue_total'],
            'income_hpp'     => $inc['hpp_total'],
            'returns'       => $retTotal,
            'returns_cogs'  => $retCogs,
            'ok' => abs(($total - $retTotal) - $inc['revenue_total']) < 0.01
                 && abs(($hpp - $retCogs) - $inc['hpp_total']) < 0.01,
         ];
    }

    // ------------------------------------------------------------------
    // UTANG DAN PIUTANG (posisi saat ini)
    // ------------------------------------------------------------------
    public function debts(): array
    {
        $today = Carbon::today();

        $payables = Purchase::with('supplier')
            ->where('status', 'confirmed')->where('payment_method', 'kredit')
            ->whereColumn('paid_amount', '<', 'total')
            ->orderBy('date')->get()
            ->map(fn($p) => (object) [
                'model' => $p,
                'left'  => round((float) $p->total - (float) $p->paid_amount, 2),
                'age'   => (int) Carbon::parse($p->date)->startOfDay()->diffInDays($today),
            ]);

        $receivables = SalesOrder::with('customer')
            ->where('status', 'terkirim')
            ->whereRaw('(paid_amount + receivable_cut) < total')
            ->orderBy('delivered_date')->get()
            ->map(fn($o) => (object) [
                'model' => $o,
                'left'  => round((float) $o->total - (float) $o->paid_amount - (float) $o->receivable_cut, 2),
                'age'   => (int) Carbon::parse($o->delivered_date)->startOfDay()->diffInDays($today),
            ]);

        // Utang dari produksi: gaji (2103) dan overhead (2101)
        $prodDebts = Production::with('item')
            ->withSum(['payments as labor_paid' => fn($q) => $q->where('kind', 'labor')], 'amount')
            ->withSum(['payments as overhead_paid' => fn($q) => $q->where('kind', 'overhead')], 'amount')
            ->where('status', 'selesai')
            ->where(fn($q) => $q->where('labor_payable', '>', 0)->orWhere('overhead_payable', '>', 0))
            ->orderBy('finished_date')->get()
            ->map(fn($p) => (object) [
                'model'         => $p,
                'labor_left'    => round((float) $p->labor_payable - (float) $p->labor_paid, 2),
                'overhead_left' => round((float) $p->overhead_payable - (float) $p->overhead_paid, 2),
                'age'           => (int) Carbon::parse($p->finished_date)->startOfDay()->diffInDays($today),
            ])
            ->filter(fn($r) => $r->labor_left > 0 || $r->overhead_left > 0)
            ->values();

        $payTotal      = round((float) $payables->sum('left'), 2);
        $recTotal      = round((float) $receivables->sum('left'), 2);
        $laborTotal    = round((float) $prodDebts->sum('labor_left'), 2);
        $overheadTotal = round((float) $prodDebts->sum('overhead_left'), 2);

        $check = function (string $label, string $acc, float $docs) {
            $ledger = $this->ledger->balance($acc);
            $diff   = round($docs - $ledger, 2);
            return [
                'label' => $label, 'acc' => $acc, 'docs' => $docs,
                'ledger' => $ledger, 'diff' => $diff, 'ok' => abs($diff) < 1,
            ];
        };

        return [
            'payables'      => $payables,
            'receivables'   => $receivables,
            'prod_debts'    => $prodDebts,
            'pay_total'     => $payTotal,
            'rec_total'     => $recTotal,
            'labor_total'   => $laborTotal,
            'overhead_total'=> $overheadTotal,
            'checks'        => [
                $check('Utang Usaha (pembelian kredit + overhead produksi)', '2101', round($payTotal + $overheadTotal, 2)),
                $check('Utang Gaji (tenaga kerja produksi)', '2103', $laborTotal),
                $check('Piutang Usaha (penjualan terkirim)', '1201', $recTotal),
            ],
        ];
    }
}