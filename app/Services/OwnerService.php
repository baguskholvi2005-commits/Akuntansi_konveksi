<?php

namespace App\Services;

use App\Models\{OwnerTransaction, Journal};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OwnerService
{
    public function __construct(
        private JournalService $journal,
        private LedgerService $ledger
    ) {}

    public function create(array $d): OwnerTransaction
    {
        return DB::transaction(function () use ($d) {
            $amount = round((float) $d['amount'], 2);
            $acc    = $d['account_code'];
            $isModal = $d['type'] === 'modal';

            // Prive mengurangi kas/bank, jadi saldo harus cukup
            if (!$isModal) {
                $saldo = $this->ledger->balance($acc);
                if ($amount > $saldo) {
                    throw new \DomainException(
                        $this->ledger->name($acc) . ' tidak cukup untuk prive. Saldo saat ini: Rp '
                        . number_format($saldo, 0, ',', '.')
                    );
                }
            }

            $t = OwnerTransaction::create([
                'number'       => DocNumber::make($isModal ? 'MDL' : 'PRV', 'owner_transactions'),
                'date'         => $d['date'],
                'type'         => $d['type'],
                'amount'       => $amount,
                'account_code' => $acc,
                'notes'        => $d['notes'] ?? null,
                'created_by'   => auth::id(),
            ]);

            $lines = $isModal
                ? [['account' => $acc,   'debit'  => $amount], ['account' => '3101', 'credit' => $amount]]
                : [['account' => '3102', 'debit'  => $amount], ['account' => $acc,   'credit' => $amount]];

            $this->journal->post(
                $d['date'],
                ($isModal ? 'Setoran modal ' : 'Prive pemilik ') . $t->number,
                $lines, $t, 'post'
            );

            return $t;
        });
    }

    public function void(OwnerTransaction $tx, string $reason): void
    {
        DB::transaction(function () use ($tx, $reason) {
            $t = OwnerTransaction::lockForUpdate()->findOrFail($tx->id);

            if ($t->status !== 'posted') {
                throw new \DomainException('Transaksi ini sudah dibatalkan.');
            }

            // Membatalkan modal berarti uang keluar lagi: saldo harus cukup
            if ($t->type === 'modal') {
                $saldo = $this->ledger->balance($t->account_code);
                if ($t->amount > $saldo) {
                    throw new \DomainException(
                        'Tidak dapat dibatalkan karena saldo ' . $this->ledger->name($t->account_code)
                        . ' (Rp ' . number_format($saldo, 0, ',', '.') . ') lebih kecil dari modal ini.'
                    );
                }
            }

            $original = Journal::where([
                'source_type' => $t->getMorphClass(),
                'source_id'   => $t->id,
                'event'       => 'post',
            ])->firstOrFail();

            $this->journal->reverse($original, "{$t->number} - {$reason}");

            $t->update(['status' => 'void', 'void_reason' => $reason]);
        });
    }
}