<?php

namespace App\Services;

use App\Models\{Account, Journal};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * $lines = [['account'=>'1101','debit'=>1000,'credit'=>0], ...]
     */
    public function post(string $date, string $description, array $lines,
                         ?Model $source = null, ?string $event = null): Journal
    {
        $debit  = round((float) collect($lines)->sum('debit'), 2);
        $credit = round((float) collect($lines)->sum('credit'), 2);

        if ($debit <= 0 || abs($debit - $credit) > 0.001) {
            throw new \DomainException("Jurnal tidak seimbang (Debit: $debit, Kredit: $credit).");
        }

        return DB::transaction(function () use ($date, $description, $lines, $source, $event) {
            if ($source && $event && Journal::where([
                'source_type' => $source->getMorphClass(),
                'source_id'   => $source->id,
                'event'       => $event,
            ])->exists()) {
                throw new \DomainException('Jurnal untuk transaksi ini sudah pernah dibuat.');
            }

            $journal = Journal::create([
                'date'        => $date,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id'   => $source?->id,
                'event'       => $event,
                'created_by'  => Auth::id(),
            ]);

            $journal->update([
                'number' => 'JU-' . date('Ym') . '-' . str_pad($journal->id, 5, '0', STR_PAD_LEFT),
            ]);

            foreach ($lines as $l) {
                $acc = Account::where('code', $l['account'])->firstOrFail();
                $journal->lines()->create([
                    'account_id' => $acc->id,
                    'debit'      => $l['debit'] ?? 0,
                    'credit'     => $l['credit'] ?? 0,
                    'memo'       => $l['memo'] ?? null,
                ]);
            }

            return $journal;
        });
    }

    /** Koreksi = jurnal pembalik (tidak menghapus), supaya jejak audit terjaga. */
    public function reverse(Journal $journal, string $reason): Journal
    {
        $lines = $journal->lines()->with('account')->get()->map(fn($l) => [
            'account' => $l->account->code,
            'debit'   => $l->credit,
            'credit'  => $l->debit,
            'memo'    => 'Pembalik ' . $journal->number,
        ])->all();

        return DB::transaction(function () use ($journal, $lines, $reason) {
            $rev = $this->post(now()->toDateString(), "Pembalik {$journal->number}: $reason", $lines);
            $rev->update(['is_reversal' => true]);
            return $rev;
        });
    }
}