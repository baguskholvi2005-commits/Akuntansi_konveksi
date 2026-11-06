<?php

namespace App\Services;

use App\Models\{SalesOrder, SalesPayment};
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function __construct(
        private InventoryService $inv,
        private JournalService $journal
    ) {}

    /** Mengubah status: menunggu_pembayaran -> dalam_produksi -> selesai (tanpa jurnal). */
    public function moveStatus(SalesOrder $order, string $to): void
    {
        $allowedFrom = ['dalam_produksi' => 'menunggu_pembayaran', 'selesai' => 'dalam_produksi'];

        DB::transaction(function () use ($order, $to, $allowedFrom) {
            $o = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if (!isset($allowedFrom[$to]) || $o->status !== $allowedFrom[$to]) {
                throw new \DomainException('Perubahan status tidak valid untuk pesanan ini.');
            }
            $o->update(['status' => $to]);
        });
    }

    /** Terima pembayaran. Sebelum kirim = uang muka (2102). Setelah kirim = pelunasan piutang (1201). */
    public function pay(SalesOrder $order, string $date, float $amount, string $accountCode): void
    {
        DB::transaction(function () use ($order, $date, $amount, $accountCode) {
            $o = SalesOrder::lockForUpdate()->findOrFail($order->id);

            $amount = round($amount, 2);
            $sisa   = round($o->total - $o->paid_amount - $o->receivable_cut, 2);

            if ($amount <= 0 || $amount > $sisa) {
                throw new \DomainException('Nominal tidak valid. Sisa tagihan: Rp ' . number_format($sisa, 0, ',', '.'));
            }
            if ($date < $o->date) {
                throw new \DomainException('Tanggal pembayaran tidak boleh lebih awal dari tanggal pesanan.');
            }

            $sudahKirim = $o->status === 'terkirim';

            $pay = SalesPayment::create([
                'sales_order_id' => $o->id,
                'date'           => $date,
                'amount'         => $amount,
                'type'           => $sudahKirim ? 'pelunasan' : 'dp',
                'account_code'   => $accountCode,
            ]);

            $this->journal->post(
                $date,
                ($sudahKirim ? 'Pelunasan piutang ' : 'Uang muka pesanan ') . $o->number,
                [
                    ['account' => $accountCode,                    'debit'  => $amount],
                    ['account' => $sudahKirim ? '1201' : '2102',   'credit' => $amount],
                ],
                $pay, 'pay'
            );

            $o->update([
                'paid_amount' => $o->paid_amount + $amount,
                // pembayaran pertama otomatis memajukan status menunggu -> produksi
                'status'      => (!$sudahKirim && $o->status === 'menunggu_pembayaran') ? 'dalam_produksi' : $o->status,
            ]);
        });
    }

    /** Kirim barang: akui pendapatan + HPP, stok produk jadi berkurang. */
    public function deliver(SalesOrder $order, string $date): void
    {
        DB::transaction(function () use ($order, $date) {
            $o = SalesOrder::with(['items.item', 'customer'])->lockForUpdate()->findOrFail($order->id);

            if ($o->status !== 'selesai') {
                throw new \DomainException('Pesanan harus berstatus "selesai" sebelum dikirim.');
            }
            if ($date < $o->date) {
                throw new \DomainException('Tanggal kirim tidak boleh lebih awal dari tanggal pesanan.');
            }
            if ($o->payment_type === 'tunai' && round($o->paid_amount, 2) < round($o->total, 2)) {
                throw new \DomainException('Penjualan tunai harus lunas sebelum barang dikirim.');
            }
            if ($o->total <= 0) {
                throw new \DomainException('Total pesanan harus lebih dari nol.');
            }

            // 1. Stok produk jadi keluar, hitung HPP (jika stok kurang, semua dibatalkan)
            $hpp = 0;
            foreach ($o->items as $row) {
                $hpp += $this->inv->out(
                    $row->item_id, $row->qty, $date, $o->number,
                    "Penjualan ke {$o->customer->name}", $o
                );
            }

            // 2. Jurnal pendapatan: DP yang sudah diterima dipindah dari utang ke pendapatan
            $total   = round($o->total, 2);
            $uangMuka = round(min($o->paid_amount, $total), 2);
            $piutang  = round($total - $uangMuka, 2);

            $lines = [];
            if ($uangMuka > 0) $lines[] = ['account' => '2102', 'debit' => $uangMuka];
            if ($piutang  > 0) $lines[] = ['account' => '1201', 'debit' => $piutang];
            $lines[] = ['account' => '4101', 'credit' => $total];

            $this->journal->post($date, "Penjualan {$o->number}", $lines, $o, 'revenue');

            // 3. Jurnal HPP
            if ($hpp > 0) {
                $this->journal->post($date, "HPP penjualan {$o->number}", [
                    ['account' => '5101', 'debit'  => $hpp],
                    ['account' => '1303', 'credit' => $hpp],
                ], $o, 'cogs');
            }

            $o->update(['status' => 'terkirim', 'delivered_date' => $date]);
        });
    }
}