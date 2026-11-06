@extends('layouts.sia')
@section('title','Laporan Pembelian')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $canOpen = in_array(auth()->user()->role, ['admin','keuangan','gudang']);
@endphp
<h4>Laporan Keuangan</h4>
@include('reports._nav')
@include('reports._period')

<div class="text-center mb-3">
  <h5 class="mb-0">{{ \App\Models\Setting::val('company_name') }}</h5>
  <h5 class="mb-0">Laporan Pembelian Bahan Baku</h5>
  <div class="text-muted">Periode {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'awal' }}
    s.d. {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Jumlah transaksi</div>
    <div class="fs-5 fw-bold">{{ $data['count'] }}</div></div></div>
  <div class="col-md-3"><div class="card card-body border-primary"><div class="small text-muted">Total pembelian</div>
    <div class="fs-5 fw-bold">Rp {{ $f($data['total']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Tunai / Kredit</div>
    <div class="fw-bold">Rp {{ $f($data['cash']) }} / Rp {{ $f($data['credit']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Uang keluar (tunai + bayar utang)</div>
    <div class="fw-bold">Rp {{ $f($data['cash_out']) }}</div></div></div>
</div>

<h6>Rincian per Pemasok</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Pemasok</th><th class="num">Transaksi</th><th class="num">Total</th>
    <th class="num">Sudah Dibayar</th><th class="num">Sisa Utang</th></tr></thead>
  <tbody>
  @forelse($data['by_supplier'] as $s)
    <tr><td>{{ $s['name'] }}</td><td class="num">{{ $s['count'] }}</td>
      <td class="num">{{ $f($s['total']) }}</td><td class="num">{{ $f($s['paid']) }}</td>
      <td class="num">{{ $f($s['outstanding']) }}</td></tr>
  @empty
    <tr><td colspan="5" class="text-center text-muted">Tidak ada pembelian pada periode ini.</td></tr>
  @endforelse
  </tbody>
</table>

<h6>Rincian per Transaksi</h6>
<table class="table table-bordered table-sm bg-white">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pemasok</th><th>Metode</th>
    <th class="num">Total</th><th class="num">Dibayar</th><th class="num">Sisa</th></tr></thead>
  <tbody>
  @forelse($data['rows'] as $p)
    <tr>
      <td>@if($canOpen)<a href="{{ route('purchases.show',$p) }}">{{ $p->number }}</a>@else{{ $p->number }}@endif</td>
      <td>{{ \Carbon\Carbon::parse($p->date)->format('d/m/Y') }}</td>
      <td>{{ $p->supplier->name }}</td>
      <td>{{ ucfirst($p->payment_method) }}</td>
      <td class="num">{{ $f($p->total) }}</td>
      <td class="num">{{ $f($p->paid_amount) }}</td>
      <td class="num">{{ $f($p->total - $p->paid_amount) }}</td>
    </tr>
  @empty
    <tr><td colspan="7" class="text-center text-muted">Tidak ada pembelian pada periode ini.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total</th>
    <th class="num">{{ $f($data['total']) }}</th>
    <th class="num">{{ $f($data['rows']->sum('paid_amount')) }}</th>
    <th class="num">{{ $f($data['rows']->sum('total') - $data['rows']->sum('paid_amount')) }}</th></tr></tfoot>
</table>
<p class="small text-muted">Hanya pembelian yang sudah diposting. Kolom <b>Dibayar</b> dan <b>Sisa</b> menunjukkan posisi saat ini
  (termasuk pembayaran yang dilakukan setelah periode ini). <b>Uang keluar</b> menghitung pembelian tunai pada periode
  ditambah pembayaran utang yang dilakukan pada periode.</p>
@endsection