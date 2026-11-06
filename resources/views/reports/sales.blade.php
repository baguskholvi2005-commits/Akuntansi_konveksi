@extends('layouts.sia')
@section('title','Laporan Penjualan')
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
  <h5 class="mb-0">Laporan Penjualan</h5>
  <div class="text-muted">Periode {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'awal' }}
    s.d. {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }} (berdasarkan tanggal kirim)</div>
  @if($data['ok'])
    <span class="badge bg-success">COCOK DENGAN LABA RUGI</span>
  @else
    <span class="badge bg-danger">TIDAK COCOK dengan Laba Rugi (penjualan Rp {{ $f($data['income_revenue']) }}, HPP Rp {{ $f($data['income_hpp']) }})</span>
  @endif
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Pesanan terkirim</div>
    <div class="fs-5 fw-bold">{{ $data['count'] }}</div></div></div>
  <div class="col-md-3"><div class="card card-body border-primary"><div class="small text-muted">Total penjualan</div>
    <div class="fs-5 fw-bold">Rp {{ $f($data['total']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">HPP</div>
    <div class="fs-5 fw-bold">Rp {{ $f($data['hpp']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body border-success"><div class="small text-muted">Laba kotor</div>
    <div class="fs-5 fw-bold">Rp {{ $f($data['profit']) }}</div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6"><div class="card card-body">
    <div class="small text-muted">Pembayaran diterima pada periode</div>
    <div class="fw-bold">Rp {{ $f($data['received']) }}</div>
    <div class="small">Uang muka: Rp {{ $f($data['dp']) }} &middot; Pelunasan piutang: Rp {{ $f($data['settlement']) }}</div>
  </div></div>
  <div class="col-md-6"><div class="card card-body">
    <div class="small text-muted">Pesanan belum terkirim (posisi saat ini)</div>
    <div class="fw-bold">{{ $data['pending_count'] }} pesanan, nilai Rp {{ $f($data['pending_total']) }}</div>
    <div class="small">Uang muka sudah diterima: Rp {{ $f($data['pending_paid']) }} (belum menjadi pendapatan)</div>
  </div></div>
</div>

<div class="alert alert-secondary py-2">Retur penjualan pada periode: <b>Rp {{ $f($data['returns']) }}</b>
  (HPP dikembalikan Rp {{ $f($data['returns_cogs']) }}).
  Penjualan bersih: <b>Rp {{ $f($data['total'] - $data['returns']) }}</b>,
  laba kotor bersih: <b>Rp {{ $f($data['profit'] - ($data['returns'] - $data['returns_cogs'])) }}</b>.
  Tabel di bawah menurut tanggal kirim, sebelum retur.</div>

<h6>Rincian per Pelanggan</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Pelanggan</th><th class="num">Pesanan</th><th class="num">Penjualan</th>
    <th class="num">HPP</th><th class="num">Laba Kotor</th></tr></thead>
  <tbody>
  @forelse($data['by_customer'] as $c)
    <tr><td>{{ $c['name'] }}</td><td class="num">{{ $c['count'] }}</td>
      <td class="num">{{ $f($c['total']) }}</td><td class="num">{{ $f($c['hpp']) }}</td>
      <td class="num">{{ $f($c['profit']) }}</td></tr>
  @empty
    <tr><td colspan="5" class="text-center text-muted">Tidak ada penjualan terkirim pada periode ini.</td></tr>
  @endforelse
  </tbody>
</table>

<h6>Rincian per Pesanan</h6>
<table class="table table-bordered table-sm bg-white">
  <thead><tr><th>Nomor</th><th>Tgl Kirim</th><th>Pelanggan</th><th>Jenis</th>
    <th class="num">Penjualan</th><th class="num">HPP</th><th class="num">Laba Kotor</th></tr></thead>
  <tbody>
  @forelse($data['rows'] as $r)
    <tr>
      <td>@if($canOpen)<a href="{{ route('orders.show',$r->order) }}">{{ $r->order->number }}</a>@else{{ $r->order->number }}@endif</td>
      <td>{{ \Carbon\Carbon::parse($r->order->delivered_date)->format('d/m/Y') }}</td>
      <td>{{ $r->order->customer->name }}</td>
      <td>{{ ucfirst($r->order->payment_type) }}</td>
      <td class="num">{{ $f($r->order->total) }}</td>
      <td class="num">{{ $f($r->hpp) }}</td>
      <td class="num">{{ $f($r->profit) }}</td>
    </tr>
  @empty
    <tr><td colspan="7" class="text-center text-muted">Tidak ada penjualan terkirim pada periode ini.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total</th>
    <th class="num">{{ $f($data['total']) }}</th><th class="num">{{ $f($data['hpp']) }}</th>
    <th class="num">{{ $f($data['profit']) }}</th></tr></tfoot>
</table>
<p class="small text-muted">Penjualan diakui saat barang terkirim. HPP diambil dari kartu persediaan. Lencana
  <b>COCOK</b> berarti total penjualan dan HPP sama dengan Laporan Laba Rugi pada periode yang sama.</p>
@endsection