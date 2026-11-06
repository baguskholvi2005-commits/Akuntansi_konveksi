<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index(Request $r, DashboardService $svc)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        // Bawaan: awal bulan ini sampai hari ini
        $from = $r->from ?: date('Y-m-01');
        $to   = $r->to ?: date('Y-m-d');

        if ($to < $from) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        // Gudang tidak boleh melihat angka keuangan
        $full  = in_array($r->user()->role, ['admin', 'keuangan', 'pemilik']);
        $ops   = $svc->operational();
        $fin   = $full ? $svc->financial($from, $to) : null;
        $chart = $full ? $svc->monthly($to) : null;

        return view('dashboard', compact('from', 'to', 'full', 'ops', 'fin', 'chart'));
    }
}