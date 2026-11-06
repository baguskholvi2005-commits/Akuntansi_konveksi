<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = [];

    // kunci => [nama tampilan, kode akun beban]
    public const CATEGORIES = [
        'gaji'         => ['Gaji', '6101'],
        'listrik'      => ['Listrik', '6102'],
        'air'          => ['Air', '6103'],
        'transportasi' => ['Transportasi', '6104'],
        'pengiriman'   => ['Pengiriman', '6105'],
        'sewa'         => ['Sewa', '6106'],
        'administrasi' => ['Administrasi', '6107'],
        'lain'         => ['Lain-lain', '6108'],
    ];
}