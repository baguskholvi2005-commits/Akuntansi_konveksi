<?php

namespace App\Http\Controllers;

use App\Models\{Account, Item};
use App\Services\{ExcelExporter, ReportExportService};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    // Laporan yang boleh diekspor oleh peran gudang
    private const WAREHOUSE_OK = ['inventory-card', 'inventory-report'];

    public function show(Request $r, string $report, string $format, ReportExportService $svc, ExcelExporter $xl)
    {
        if (auth()->user()->role === 'gudang' && !in_array($report, self::WAREHOUSE_OK)) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'as_of' => 'nullable|date']);

        $from = $r->from ?: null;
        $to   = $r->to ?: date('Y-m-d');
        $asOf = $r->as_of ?: date('Y-m-d');

        if ($from && $to < $from) {
            abort(422, 'Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        $account = $item = null;
        if ($report === 'ledger') {
            $id = $r->validate(['account_id' => 'required|exists:accounts,id'])['account_id'];
            $account = Account::findOrFail($id);
        }
        if ($report === 'inventory-card') {
            $id = $r->validate(['item_id' => 'required|exists:items,id'])['item_id'];
            $item = Item::with('unit')->findOrFail($id);
        }

        $data = match ($report) {
            'income'           => $svc->income($from, $to),
            'balance'          => $svc->balance($asOf),
            'cashflow'         => $svc->cashflow($from, $to),
            'equity'           => $svc->equity($from, $to),
            'trial'            => $svc->trial($asOf),
            'ledger'           => $svc->ledger($account, $from, $r->to ?: null),
            'inventory-card'   => $svc->inventoryCard($item, $from, $r->to ?: null),
            'inventory-report' => $svc->inventoryReport(),
            'purchases'        => $svc->purchases($from, $to),
            'sales'            => $svc->sales($from, $to),
            'debts'            => $svc->debts(),
            default            => abort(404),
        };

        if ($format === 'pdf') {
            return Pdf::loadView('exports.pdf', ['r' => $data])
                ->setPaper('a4', $data['landscape'] ? 'landscape' : 'portrait')
                ->download($data['file'] . '.pdf');
        }

        return $xl->download($data, $data['file']);
    }
}