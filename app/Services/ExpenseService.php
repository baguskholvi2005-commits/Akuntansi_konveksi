<?php

namespace App\Services;

use App\Models\{Expense, Journal};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private JournalService $journal,
        private LedgerService $ledger
    ) {}

    public function create(array $d, ?UploadedFile $file = null): Expense
    {
        return DB::transaction(function () use ($d, $file) {
            $amount = round((float) $d['amount'], 2);
            [$label, $accCode] = Expense::CATEGORIES[$d['category']];
            $payAcc = $d['payment_account'];

            $saldo = $this->ledger->balance($payAcc);
            if ($amount > $saldo) {
                throw new \DomainException(
                    $this->ledger->name($payAcc) . ' tidak cukup. Saldo saat ini: Rp ' . number_format($saldo, 0, ',', '.')
                    . '. Catat modal awal terlebih dahulu bila belum ada.'
                );
            }

            $e = Expense::create([
                'number'               => DocNumber::make('EXP', 'expenses'),
                'date'                 => $d['date'],
                'category'             => $d['category'],
                'account_code'         => $accCode,
                'description'          => $d['description'],
                'amount'               => $amount,
                'payment_account_code' => $payAcc,
                'created_by'           => Auth::id(),
            ]);

            $this->journal->post($d['date'], "Beban {$label}: {$d['description']} ({$e->number})", [
                ['account' => $accCode, 'debit'  => $amount],
                ['account' => $payAcc,  'credit' => $amount],
            ], $e, 'post');

            // File disimpan paling akhir; kalau gagal, semua dibatalkan
            if ($file) {
                $e->update(['proof_path' => $file->store('bukti', 'public')]);
            }

            return $e;
        });
    }

    /** Batalkan lewat jurnal pembalik. Data asli tetap ada dengan status void. */
    public function void(Expense $expense, string $reason): void
    {
        DB::transaction(function () use ($expense, $reason) {
            $e = Expense::lockForUpdate()->findOrFail($expense->id);

            if ($e->status !== 'posted') {
                throw new \DomainException('Pengeluaran ini sudah dibatalkan.');
            }

            $original = Journal::where([
                'source_type' => $e->getMorphClass(),
                'source_id'   => $e->id,
                'event'       => 'post',
            ])->firstOrFail();

            $this->journal->reverse($original, "{$e->number} - {$reason}");

            $e->update(['status' => 'void', 'void_reason' => $reason]);
        });
    }
}