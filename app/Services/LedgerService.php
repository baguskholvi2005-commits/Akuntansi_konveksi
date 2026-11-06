<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /** Saldo akun sesuai saldo normalnya, dihitung dari jurnal. */
    public function balance(string $code): float
    {
        $acc = Account::where('code', $code)->firstOrFail();

        $row = DB::table('journal_lines')
            ->where('account_id', $acc->id)
            ->selectRaw('COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c')
            ->first();

        $saldo = $acc->normal_balance === 'debit' ? $row->d - $row->c : $row->c - $row->d;

        return round((float) $saldo, 2);
    }

    public function name(string $code): string
    {
        return Account::where('code', $code)->value('name') ?? $code;
    }
}