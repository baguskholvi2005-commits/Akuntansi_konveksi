<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocNumber
{
    public static function make(string $prefix, string $table): string
    {
        $ym   = date('Ym');
        $last = DB::table($table)->where('number', 'like', "$prefix-$ym-%")->max('number');
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $ym, $seq);
    }
}