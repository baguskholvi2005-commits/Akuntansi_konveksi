<?php

namespace App\Services;

use App\Models\{Account, Item};
use Carbon\Carbon;

class ReportExportService
{
    public function __construct(
        private FinancialReportService $fin,
        private ReportService $rep,
        private InventoryReportService $inv,
        private TradeReportService $trade
    ) {}

    // ---------------- pembantu ----------------

    private function d($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '';
    }

    private function period(?string $from, ?string $to): string
    {
        return 'Periode ' . ($from ? $this->d($from) : 'awal') . ' s.d. ' . ($to ? $this->d($to) : 'sekarang');
    }

    /** Satu baris tabel. $indent = menjorok ke dalam (untuk rincian akun). */
    private function row(array $cells, bool $bold = false, int $indent = 0): array
    {
        return ['cells' => $cells, 'bold' => $bold, 'indent' => $indent];
    }

    /** Definisi kolom. Tipe: text, money (tanpa desimal), qty (2 desimal). */
    private function col(string $label, string $type = 'text'): array
    {
        return ['label' => $label, 'type' => $type];
    }

    private function table(?string $heading, array $columns, array $rows): array
    {
        return ['heading' => $heading, 'columns' => $columns, 'rows' => $rows];
    }

    /** Tabel dua kolom: Keterangan dan Jumlah. */
    private function simple(array $rows, ?string $heading = null): array
    {
        return $this->table($heading, [$this->col('Keterangan'), $this->col('Jumlah (Rp)', 'money')], $rows);
    }

    private function make(string $title, string $subtitle, string $file, array $tables): array
    {
        $maxCols = max(array_map(fn($t) => count($t['columns']), $tables));

        return [
            'title'     => $title,
            'subtitle'  => $subtitle,
            'file'      => $file . '-' . date('Ymd'),
            'tables'    => $tables,
            'landscape' => $maxCols >= 8,
        ];
    }

    // ---------------- LABA RUGI ----------------

    public function income(?string $from, string $to): array
    {
        $x = $this->fin->incomeStatement($from, $to);
        $rows = [];

        $rows[] = $this->row(['Pendapatan', null], true);
        foreach ($x['revenue'] as $a) $rows[] = $this->row([$a['code'] . ' ' . $a['name'], $a['amount']], false, 1);
        $rows[] = $this->row(['Total Pendapatan', $x['revenue_total']], true);

        $rows[] = $this->row(['Harga Pokok Penjualan', null], true);
        foreach ($x['hpp'] as $a) $rows[] = $this->row([$a['code'] . ' ' . $a['name'], $a['amount']], false, 1);
        $rows[] = $this->row(['Total HPP', $x['hpp_total']], true);

        $rows[] = $this->row(['LABA KOTOR', $x['gross']], true);

        $rows[] = $this->row(['Beban Operasional', null], true);
        foreach ($x['expenses'] as $a) $rows[] = $this->row([$a['code'] . ' ' . $a['name'], $a['amount']], false, 1);
        $rows[] = $this->row(['Total Beban Operasional', $x['expense_total']], true);

        $rows[] = $this->row([$x['net'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH', $x['net']], true);

        return $this->make('Laporan Laba Rugi', $this->period($from, $to), 'laba-rugi', [$this->simple($rows)]);
    }

    // ---------------- NERACA ----------------

    public function balance(string $asOf): array
    {
        $x = $this->fin->balanceSheet($asOf);
        $rows = [];

        $rows[] = $this->row(['ASET', null], true);
        foreach ($x['assets'] as $a) $rows[] = $this->row([$a['code'] . ' ' . $a['name'], $a['amount']], false, 1);
        $rows[] = $this->row(['TOTAL ASET', $x['asset_total']], true);

        $rows[] = $this->row(['LIABILITAS', null], true);
        foreach ($x['liabilities'] as $a) $rows[] = $this->row([$a['code'] . ' ' . $a['name'], $a['amount']], false, 1);
        $rows[] = $this->row(['Total Liabilitas', $x['liability_total']], true);

        $rows[] = $this->row(['EKUITAS', null], true);
        foreach ($x['equity'] as $a) $rows[] = $this->row([$a['code'] . ' ' . $a['name'], $a['amount']], false, 1);
        $rows[] = $this->row(['Laba (rugi) berjalan', $x['profit']], false, 1);
        $rows[] = $this->row(['Total Ekuitas', $x['equity_total']], true);

        $rows[] = $this->row(['TOTAL LIABILITAS + EKUITAS', $x['liab_equity_total']], true);

        $status = $x['balanced'] ? 'SEIMBANG' : 'TIDAK SEIMBANG';

        return $this->make('Laporan Posisi Keuangan (Neraca)', 'Per ' . $this->d($asOf) . ' - ' . $status, 'neraca', [$this->simple($rows)]);
    }

    // ---------------- ARUS KAS ----------------

    public function cashflow(?string $from, string $to): array
    {
        $x = $this->fin->cashFlow($from, $to);
        $rows = [];

        $rows[] = $this->row(['Arus Kas dari Aktivitas Operasi', null], true);
        foreach ($x['operating'] as $a) $rows[] = $this->row([$a['label'], $a['amount']], false, 1);
        $rows[] = $this->row(['Kas bersih dari aktivitas operasi', $x['operating_total']], true);

        $rows[] = $this->row(['Arus Kas dari Aktivitas Investasi', null], true);
        $rows[] = $this->row(['Kas bersih dari aktivitas investasi', $x['investing_total']], true);

        $rows[] = $this->row(['Arus Kas dari Aktivitas Pendanaan', null], true);
        foreach ($x['financing'] as $a) $rows[] = $this->row([$a['label'], $a['amount']], false, 1);
        $rows[] = $this->row(['Kas bersih dari aktivitas pendanaan', $x['financing_total']], true);

        $rows[] = $this->row(['Kenaikan (penurunan) kas bersih', $x['net']], true);
        $rows[] = $this->row(['Saldo kas dan bank awal', $x['opening']], false);
        $rows[] = $this->row(['Saldo kas dan bank akhir', $x['closing']], true);

        $status = $x['ok'] ? 'Cocok dengan saldo Kas + Bank' : 'TIDAK COCOK dengan saldo Kas + Bank';

        return $this->make('Laporan Arus Kas', $this->period($from, $to) . ' - ' . $status, 'arus-kas', [$this->simple($rows)]);
    }

    // ---------------- PERUBAHAN MODAL ----------------

    public function equity(?string $from, string $to): array
    {
        $x = $this->fin->equityChange($from, $to);

        $rows = [
            $this->row(['Modal awal periode', $x['opening']], true),
            $this->row(['Tambahan modal (setoran pemilik)', $x['capital']], false, 1),
            $this->row([$x['profit'] >= 0 ? 'Laba bersih periode' : 'Rugi bersih periode', $x['profit']], false, 1),
            $this->row(['Prive (pengambilan pemilik)', -$x['prive']], false, 1),
            $this->row(['Modal akhir periode', $x['closing']], true),
        ];

        $status = $x['ok'] ? 'Cocok dengan Neraca' : 'TIDAK COCOK dengan Neraca';

        return $this->make('Laporan Perubahan Modal', $this->period($from, $to) . ' - ' . $status, 'perubahan-modal', [$this->simple($rows)]);
    }

    // ---------------- NERACA SALDO ----------------

    public function trial(string $asOf): array
    {
        $x = $this->rep->trialBalance($asOf);
        $rows = [];

        foreach ($x['rows'] as $r) {
            $rows[] = $this->row([$r['code'], $r['name'], $r['debit'], $r['credit'], $r['sd'] ?: null, $r['sk'] ?: null]);
        }
        $t = $x['total'];
        $rows[] = $this->row(['', 'Total', $t['debit'], $t['credit'], $t['sd'], $t['sk']], true);

        $cols = [
            $this->col('Kode'), $this->col('Nama Akun'),
            $this->col('Mutasi Debit', 'money'), $this->col('Mutasi Kredit', 'money'),
            $this->col('Saldo Debit', 'money'), $this->col('Saldo Kredit', 'money'),
        ];
        $status = $x['balanced'] ? 'SEIMBANG' : 'TIDAK SEIMBANG';

        return $this->make('Neraca Saldo', 'Per ' . $this->d($asOf) . ' - ' . $status, 'neraca-saldo', [$this->table(null, $cols, $rows)]);
    }

    // ---------------- BUKU BESAR ----------------

    public function ledger(Account $acc, ?string $from, ?string $to): array
    {
        $x = $this->rep->ledger($acc, $from, $to);
        $rows = [];

        $rows[] = $this->row(['', '', 'Saldo awal', null, null, $x['opening']], true);
        foreach ($x['rows'] as $l) {
            $rows[] = $this->row([
                $this->d($l['date']), $l['number'], $l['description'],
                $l['debit'] ?: null, $l['credit'] ?: null, $l['balance'],
            ]);
        }
        $rows[] = $this->row(['', '', 'Total mutasi', $x['total_debit'], $x['total_credit'], null], true);
        $rows[] = $this->row(['', '', 'Saldo akhir', null, null, $x['closing']], true);

        $cols = [
            $this->col('Tanggal'), $this->col('No. Jurnal'), $this->col('Keterangan'),
            $this->col('Debit', 'money'), $this->col('Kredit', 'money'), $this->col('Saldo', 'money'),
        ];

        return $this->make(
            'Buku Besar - ' . $acc->code . ' ' . $acc->name,
            $this->period($from, $to) . ' - Saldo normal: ' . $acc->normal_balance,
            'buku-besar-' . $acc->code,
            [$this->table(null, $cols, $rows)]
        );
    }

    // ---------------- KARTU PERSEDIAAN ----------------

    public function inventoryCard(Item $item, ?string $from, ?string $to): array
    {
        $x = $this->inv->card($item, $from, $to);
        $rows = [];
        $o = $x['opening'];

        $rows[] = $this->row(['', '', 'Saldo awal', null, null, null, null, null, null, $o['qty'], $o['cost'], $o['value']], true);

        foreach ($x['rows'] as $m) {
            $in  = $m->in_qty > 0;
            $out = $m->out_qty > 0;
            $rows[] = $this->row([
                $this->d($m->date), $m->reference, $m->description,
                $in ? (float) $m->in_qty : null, $in ? (float) $m->in_cost : null, $in ? (float) $m->in_value : null,
                $out ? (float) $m->out_qty : null, $out ? (float) $m->out_cost : null, $out ? (float) $m->out_value : null,
                (float) $m->balance_qty, (float) $m->balance_cost, (float) $m->balance_value,
            ]);
        }

        $t = $x['total'];
        $rows[] = $this->row(['', '', 'Total mutasi', $t['in_qty'], null, $t['in_value'], $t['out_qty'], null, $t['out_value'], null, null, null], true);
        $c = $x['closing'];
        $rows[] = $this->row(['', '', 'Saldo akhir', null, null, null, null, null, null, $c['qty'], $c['cost'], $c['value']], true);

        $cols = [
            $this->col('Tanggal'), $this->col('Referensi'), $this->col('Keterangan'),
            $this->col('Masuk Qty', 'qty'), $this->col('Masuk Harga', 'qty'), $this->col('Masuk Nilai', 'money'),
            $this->col('Keluar Qty', 'qty'), $this->col('Keluar Harga', 'qty'), $this->col('Keluar Nilai', 'money'),
            $this->col('Saldo Qty', 'qty'), $this->col('Saldo Harga', 'qty'), $this->col('Saldo Nilai', 'money'),
        ];

        return $this->make(
            'Kartu Persediaan - ' . $item->code . ' ' . $item->name,
            $this->period($from, $to) . ' - Satuan: ' . $item->unit->name . ' - Metode rata-rata tertimbang',
            'kartu-persediaan-' . $item->code,
            [$this->table(null, $cols, $rows)]
        );
    }

    // ---------------- LAPORAN PERSEDIAAN ----------------

    public function inventoryReport(): array
    {
        $x = $this->inv->report();

        $summary = $this->simple([
            $this->row(['Bahan baku', $x['raw_value']]),
            $this->row(['Barang dalam proses', $x['wip_value']]),
            $this->row(['Produk jadi', $x['fg_value']]),
            $this->row(['Total persediaan', $x['total_value']], true),
        ], 'Ringkasan Nilai Persediaan');

        $stockCols = [
            $this->col('Kode'), $this->col('Nama'), $this->col('Satuan'),
            $this->col('Stok', 'qty'), $this->col('Batas Min.', 'qty'), $this->col('Harga Rata-rata', 'qty'),
            $this->col('Nilai (Rp)', 'money'), $this->col('Status'),
        ];
        $stockRows = function ($list, float $sum) {
            $rows = [];
            foreach ($list as $i) {
                $rows[] = $this->row([$i->code, $i->name, $i->unit, $i->qty, $i->min, $i->cost, $i->value, $i->status]);
            }
            $rows[] = $this->row(['Total', '', '', '', '', '', $sum, ''], true);
            return $rows;
        };

        $wipRows = [];
        foreach ($x['wip'] as $p) {
            $wipRows[] = $this->row([$p->number, $this->d($p->date), $p->item->name, (float) $p->qty, (float) $p->material_cost]);
        }
        $wipRows[] = $this->row(['Total', '', '', '', $x['wip_value']], true);

        $recRows = [];
        foreach ($x['reconciliation'] as $c) {
            $recRows[] = $this->row([$c['label'], $c['acc'], $c['stock'], $c['ledger'], $c['diff'], $c['ok'] ? 'cocok' : 'selisih']);
        }

        return $this->make('Laporan Persediaan', 'Kondisi per ' . now()->format('d/m/Y H:i'), 'laporan-persediaan', [
            $summary,
            $this->table('Bahan Baku', $stockCols, $stockRows($x['raw'], $x['raw_value'])),
            $this->table('Produk Jadi', $stockCols, $stockRows($x['fg'], $x['fg_value'])),
            $this->table('Barang Dalam Proses', [
                $this->col('Nomor Produksi'), $this->col('Tanggal Mulai'), $this->col('Produk'),
                $this->col('Jumlah', 'qty'), $this->col('Biaya Bahan', 'money'),
            ], $wipRows),
            $this->table('Pencocokan dengan Jurnal', [
                $this->col('Persediaan'), $this->col('Akun Jurnal'),
                $this->col('Nilai Stok', 'money'), $this->col('Saldo Jurnal', 'money'),
                $this->col('Selisih', 'qty'), $this->col('Hasil'),
            ], $recRows),
        ]);
    }

    // ---------------- PEMBELIAN ----------------

    public function purchases(?string $from, string $to): array
    {
        $x = $this->trade->purchases($from, $to);

        $summary = $this->simple([
            $this->row(['Jumlah transaksi', $x['count']]),
            $this->row(['Total pembelian', $x['total']], true),
            $this->row(['Pembelian tunai', $x['cash']], false, 1),
            $this->row(['Pembelian kredit', $x['credit']], false, 1),
            $this->row(['Pembayaran utang pada periode', $x['debt_payments']]),
            $this->row(['Uang keluar (tunai + bayar utang)', $x['cash_out']], true),
        ], 'Ringkasan');

        $supRows = [];
        $sT = $sP = $sO = 0;
        foreach ($x['by_supplier'] as $s) {
            $supRows[] = $this->row([$s['name'], $s['count'], $s['total'], $s['paid'], $s['outstanding']]);
            $sT += $s['total']; $sP += $s['paid']; $sO += $s['outstanding'];
        }
        $supRows[] = $this->row(['Total', '', round($sT, 2), round($sP, 2), round($sO, 2)], true);

        $trxRows = [];
        $paid = 0;
        foreach ($x['rows'] as $p) {
            $trxRows[] = $this->row([
                $p->number, $this->d($p->date), $p->supplier->name, ucfirst($p->payment_method),
                (float) $p->total, (float) $p->paid_amount, round((float) $p->total - (float) $p->paid_amount, 2),
            ]);
            $paid += (float) $p->paid_amount;
        }
        $trxRows[] = $this->row(['Total', '', '', '', $x['total'], round($paid, 2), round($x['total'] - $paid, 2)], true);

        return $this->make('Laporan Pembelian Bahan Baku', $this->period($from, $to), 'pembelian', [
            $summary,
            $this->table('Rincian per Pemasok', [
                $this->col('Pemasok'), $this->col('Transaksi', 'money'), $this->col('Total', 'money'),
                $this->col('Dibayar', 'money'), $this->col('Sisa Utang', 'money'),
            ], $supRows),
            $this->table('Rincian per Transaksi', [
                $this->col('Nomor'), $this->col('Tanggal'), $this->col('Pemasok'), $this->col('Metode'),
                $this->col('Total', 'money'), $this->col('Dibayar', 'money'), $this->col('Sisa', 'money'),
            ], $trxRows),
        ]);
    }

    // ---------------- PENJUALAN ----------------

    public function sales(?string $from, string $to): array
    {
        $x = $this->trade->sales($from, $to);

        $summary = $this->simple([
            $this->row(['Pesanan terkirim', $x['count']]),
            $this->row(['Total penjualan', $x['total']], true),
            $this->row(['HPP', $x['hpp']], false, 1),
            $this->row(['Laba kotor', $x['profit']], true),
            $this->row(['Retur penjualan pada periode', $x['returns']]),
            $this->row(['Penjualan bersih (setelah retur)', round($x['total'] - $x['returns'], 2)], true),
            $this->row(['Uang muka diterima pada periode', $x['dp']]),
            $this->row(['Pelunasan piutang pada periode', $x['settlement']]),
            $this->row(['Total pembayaran diterima', $x['received']], true),
            $this->row(['Pesanan belum terkirim (jumlah, posisi saat ini)', $x['pending_count']]),
            $this->row(['Nilai pesanan belum terkirim', $x['pending_total']]),
        ], 'Ringkasan');

        $cusRows = [];
        $cT = $cH = $cP = 0;
        foreach ($x['by_customer'] as $c) {
            $cusRows[] = $this->row([$c['name'], $c['count'], $c['total'], $c['hpp'], $c['profit']]);
            $cT += $c['total']; $cH += $c['hpp']; $cP += $c['profit'];
        }
        $cusRows[] = $this->row(['Total', '', round($cT, 2), round($cH, 2), round($cP, 2)], true);

        $ordRows = [];
        foreach ($x['rows'] as $r) {
            $ordRows[] = $this->row([
                $r->order->number, $this->d($r->order->delivered_date), $r->order->customer->name,
                ucfirst($r->order->payment_type), (float) $r->order->total, $r->hpp, $r->profit,
            ]);
        }
        $ordRows[] = $this->row(['Total', '', '', '', $x['total'], $x['hpp'], $x['profit']], true);

        $status = $x['ok'] ? 'Cocok dengan Laba Rugi' : 'TIDAK COCOK dengan Laba Rugi';

        return $this->make('Laporan Penjualan', $this->period($from, $to) . ' (tanggal kirim) - ' . $status, 'penjualan', [
            $summary,
            $this->table('Rincian per Pelanggan', [
                $this->col('Pelanggan'), $this->col('Pesanan', 'money'), $this->col('Penjualan', 'money'),
                $this->col('HPP', 'money'), $this->col('Laba Kotor', 'money'),
            ], $cusRows),
            $this->table('Rincian per Pesanan', [
                $this->col('Nomor'), $this->col('Tgl Kirim'), $this->col('Pelanggan'), $this->col('Jenis'),
                $this->col('Penjualan', 'money'), $this->col('HPP', 'money'), $this->col('Laba Kotor', 'money'),
            ], $ordRows),
        ]);
    }

    // ---------------- UTANG DAN PIUTANG ----------------

    public function debts(): array
    {
        $x = $this->trade->debts();

        $payRows = [];
        foreach ($x['payables'] as $r) {
            $payRows[] = $this->row([
                $r->model->number, $this->d($r->model->date), $r->model->supplier->name,
                (float) $r->model->total, $r->left, $r->age,
            ]);
        }
        $payRows[] = $this->row(['Total', '', '', '', $x['pay_total'], ''], true);

        $recRows = [];
        foreach ($x['receivables'] as $r) {
            $recRows[] = $this->row([
                $r->model->number, $this->d($r->model->delivered_date), $r->model->customer->name,
                (float) $r->model->total, $r->left, $r->age,
            ]);
        }
        $recRows[] = $this->row(['Total', '', '', '', $x['rec_total'], ''], true);

        $chkRows = [];
        foreach ($x['checks'] as $c) {
            $chkRows[] = $this->row([$c['label'], $c['acc'], $c['docs'], $c['ledger'], $c['diff'], $c['ok'] ? 'cocok' : 'selisih']);
        }

        $cols = fn(string $party, string $dateLabel, string $leftLabel) => [
            $this->col('Nomor'), $this->col($dateLabel), $this->col($party),
            $this->col('Total', 'money'), $this->col($leftLabel, 'money'), $this->col('Umur (hari)', 'money'),
        ];

        return $this->make('Utang dan Piutang', 'Posisi per ' . now()->format('d/m/Y H:i'), 'utang-piutang', [
            $this->table('Utang Usaha (belum dibayar)', $cols('Pemasok', 'Tanggal', 'Sisa Utang'), $payRows),
            $this->table('Piutang Usaha (belum ditagih)', $cols('Pelanggan', 'Tgl Kirim', 'Sisa Piutang'), $recRows),
            $this->table('Pencocokan dengan Jurnal', [
                $this->col('Jenis'), $this->col('Akun'), $this->col('Total Dokumen', 'money'),
                $this->col('Saldo Jurnal', 'money'), $this->col('Selisih', 'money'), $this->col('Hasil'),
            ], $chkRows),
        ]);
    }
}