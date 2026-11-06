<?php

namespace App\Services;

use App\Models\{Purchase, PurchasePayment};
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private InventoryService $inv,
        private JournalService $journal,
        private LedgerService $ledger
    ) {}

    public function confirm(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase = Purchase::with(['items', 'supplier'])->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== 'draft') {
                throw new \DomainException('Pembelian sudah diposting.');
            }
            if ($purchase->items->isEmpty()) {
                throw new \DomainException('Pembelian tidak memiliki item.');
            }

            $tunai    = $purchase->payment_method === 'tunai';
            $sub      = round((float) $purchase->items->sum('subtotal'), 2);
            $discount = round((float) $purchase->discount, 2);
            $shipping = round((float) $purchase->shipping_cost, 2);
            $net      = round($sub - $discount + $shipping, 2);

            if ($discount > $sub || $net <= 0) {
                throw new \DomainException('Diskon tidak boleh melebihi subtotal barang.');
            }

            if ($tunai) {
                $saldo = $this->ledger->balance('1101');
                if ($net > $saldo) {
                    throw new \DomainException(
                        'Kas tidak cukup untuk pembelian tunai. Dibutuhkan Rp ' . number_format($net, 0, ',', '.')
                        . ', saldo Kas Rp ' . number_format($saldo, 0, ',', '.')
                        . '. Catat modal, atau ubah pembayaran menjadi kredit.'
                    );
                }
            }

            // Diskon dan ongkir dibagi proporsional ke tiap barang; barang terakhir menyerap sisa pembulatan
            $adj = round($shipping - $discount, 2);
            $n = $purchase->items->count();
            $i = 0;
            $allocated = 0;
            $total = 0;

            foreach ($purchase->items as $row) {
                $i++;
                $base = (float) $row->subtotal;
                $eff  = $i === $n
                    ? round($net - $allocated, 2)
                    : round($base + ($sub > 0 ? $adj * $base / $sub : 0), 2);
                $allocated += $eff;

                $total += $this->inv->in(
                    $row->item_id, $row->qty, $eff / $row->qty,
                    $purchase->date, $purchase->number,
                    "Pembelian dari {$purchase->supplier->name}", $purchase
                );
            }

            $this->journal->post(
                $purchase->date, "Pembelian bahan baku {$purchase->number}",
                [
                    ['account' => '1301',                     'debit'  => $total],
                    ['account' => $tunai ? '1101' : '2101',   'credit' => $total],
                ],
                $purchase, 'confirm'
            );

            $purchase->update([
                'total'       => $total,
                'paid_amount' => $tunai ? $total : 0,
                'status'      => 'confirmed',
            ]);
        });
    }

    public function pay(Purchase $purchase, string $date, float $amount, string $accountCode): void
    {
        DB::transaction(function () use ($purchase, $date, $amount, $accountCode) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== 'confirmed' || $purchase->payment_method !== 'kredit') {
                throw new \DomainException('Hanya pembelian kredit yang sudah dikonfirmasi yang dapat dibayar.');
            }

            $sisa = round($purchase->total - $purchase->paid_amount, 2);
            if ($amount <= 0 || round($amount, 2) > $sisa) {
                throw new \DomainException('Nominal tidak valid. Sisa utang: Rp ' . number_format($sisa, 0, ',', '.'));
            }

            $saldo = $this->ledger->balance($accountCode);
            if (round($amount, 2) > $saldo) {
                throw new \DomainException(
                    $this->ledger->name($accountCode) . ' tidak cukup. Saldo: Rp ' . number_format($saldo, 0, ',', '.')
                );
            }

            $pay = PurchasePayment::create([
                'purchase_id' => $purchase->id, 'date' => $date,
                'amount' => $amount, 'account_code' => $accountCode,
            ]);

            $this->journal->post($date, "Pembayaran utang {$purchase->number}", [
                ['account' => '2101',       'debit'  => $amount],
                ['account' => $accountCode, 'credit' => $amount],
            ], $pay, 'pay');

            $purchase->update(['paid_amount' => $purchase->paid_amount + $amount]);
        });
    }
}