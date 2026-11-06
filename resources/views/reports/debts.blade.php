@extends('layouts.sia')
@section('title','Utang dan Piutang')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $canOpen = in_array(auth()->user()->role, ['admin','keuangan','gudang']);
  $allOk = collect($data['checks'])->every(fn($c) => $c['ok']);
@endphp
<h4>Laporan Keuangan</h4>
@include('reports._nav')

<div class="d-flex justify-content-between mb-2">
  <div>
    <h5 class="mb-0">Utang dan Piutang</h5>
    <div class="text-muted">Posisi per {{ now()->format('d/m/Y H:i') }}</div>
  </div>  
  <div class="d-flex gap-2 no-print align-self-start">
    @include('partials.export')
    <button onclick="window.print()" class="btn btn-outline-secondary">Cetak</button>
  </div> <button onclick="window.print()" class="btn btn-outline-secondary no-print align-self-start">Cetak</button>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6"><div class="card card-body border-danger"><div class="small text-muted">Total utang usaha ke pemasok</div>
    <div class="fs-5 fw-bold">Rp {{ $f($data['pay_total']) }}</div></div></div>
  <div class="col-md-6"><div class="card card-body border-success"><div class="small text-muted">Total piutang dari pelanggan</div>
    <div class="fs-5 fw-bold">Rp {{ $f($data['rec_total']) }}</div></div></div>
</div>

<h6>Utang Usaha (belum dibayar)</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pemasok</th><th class="num">Total</th>
    <th class="num">Sisa Utang</th><th class="num">Umur (hari)</th></tr></thead>
  <tbody>
  @forelse($data['payables'] as $r)
    <tr>
      <td>@if($canOpen)<a href="{{ route('purchases.show',$r->model) }}">{{ $r->model->number }}</a>@else{{ $r->model->number }}@endif</td>
      <td>{{ \Carbon\Carbon::parse($r->model->date)->format('d/m/Y') }}</td>
      <td>{{ $r->model->supplier->name }}</td>
      <td class="num">{{ $f($r->model->total) }}</td>
      <td class="num">{{ $f($r->left) }}</td>
      <td class="num {{ $r->age > 30 ? 'text-danger fw-bold' : '' }}">{{ $r->age }}</td>
    </tr>
  @empty
    <tr><td colspan="6" class="text-center text-muted">Tidak ada utang.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total</th>
    <th class="num">{{ $f($data['pay_total']) }}</th><th></th></tr></tfoot>
</table>

<h6>Piutang Usaha (belum ditagih)</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Nomor</th><th>Tgl Kirim</th><th>Pelanggan</th><th class="num">Total</th>
    <th class="num">Sisa Piutang</th><th class="num">Umur (hari)</th></tr></thead>
  <tbody>
  @forelse($data['receivables'] as $r)
    <tr>
      <td>@if($canOpen)<a href="{{ route('orders.show',$r->model) }}">{{ $r->model->number }}</a>@else{{ $r->model->number }}@endif</td>
      <td>{{ \Carbon\Carbon::parse($r->model->delivered_date)->format('d/m/Y') }}</td>
      <td>{{ $r->model->customer->name }}</td>
      <td class="num">{{ $f($r->model->total) }}</td>
      <td class="num">{{ $f($r->left) }}</td>
      <td class="num {{ $r->age > 30 ? 'text-danger fw-bold' : '' }}">{{ $r->age }}</td>
    </tr>
  @empty
    <tr><td colspan="6" class="text-center text-muted">Tidak ada piutang.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total</th>
    <th class="num">{{ $f($data['rec_total']) }}</th><th></th></tr></tfoot>
</table>

<h6>Utang Produksi (gaji dan overhead yang belum dibayar)</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Nomor Produksi</th><th>Tgl Selesai</th><th>Produk</th>
    <th class="num">Sisa Utang Gaji</th><th class="num">Sisa Utang Overhead</th><th class="num">Umur (hari)</th></tr></thead>
  <tbody>
  @forelse($data['prod_debts'] as $r)
    <tr>
      <td>@if($canOpen)<a href="{{ route('productions.show',$r->model) }}">{{ $r->model->number }}</a>@else{{ $r->model->number }}@endif</td>
      <td>{{ \Carbon\Carbon::parse($r->model->finished_date)->format('d/m/Y') }}</td>
      <td>{{ $r->model->item->name }}</td>
      <td class="num">{{ $f($r->labor_left) }}</td>
      <td class="num">{{ $f($r->overhead_left) }}</td>
      <td class="num {{ $r->age > 30 ? 'text-danger fw-bold' : '' }}">{{ $r->age }}</td>
    </tr>
  @empty
    <tr><td colspan="6" class="text-center text-muted">Tidak ada utang produksi.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total</th>
    <th class="num">{{ $f($data['labor_total']) }}</th><th class="num">{{ $f($data['overhead_total']) }}</th><th></th></tr></tfoot>
</table>

<h6>Pencocokan dengan Jurnal
  @if($allOk)<span class="badge bg-success">COCOK</span>
  @else<span class="badge bg-warning text-dark">ADA SELISIH, baca catatan</span>@endif
</h6>
<table class="table table-bordered table-sm bg-white">
  <thead><tr><th>Jenis</th><th>Akun</th><th class="num">Total Dokumen</th><th class="num">Saldo Jurnal</th>
    <th class="num">Selisih</th><th>Hasil</th></tr></thead>
  <tbody>
  @foreach($data['checks'] as $c)
    <tr><td>{{ $c['label'] }}</td><td>{{ $c['acc'] }}</td>
      <td class="num">{{ $f($c['docs']) }}</td><td class="num">{{ $f($c['ledger']) }}</td>
      <td class="num">{{ $f($c['diff']) }}</td>
      <td><span class="badge bg-{{ $c['ok'] ? 'success' : 'warning text-dark' }}">{{ $c['ok'] ? 'cocok' : 'selisih' }}</span></td></tr>
  @endforeach
  </tbody>
</table>
<p class="small text-muted">Total dokumen dibandingkan dengan saldo akun di jurnal. Selisih berarti ada jurnal ke akun utang
  yang tidak berasal dari dokumen, misalnya <b>Jurnal Manual</b>. Lunasi utang lewat halaman transaksinya
  (pembelian atau produksi), bukan lewat jurnal manual.</p>
@endsection