@extends('layouts.sia')
@section('title','Laporan Persediaan')
@section('content')
@php
  $fq = fn($v) => number_format($v, 2, ',', '.');
  $fv = fn($v) => number_format($v, 0, ',', '.');
  $badge = ['aman' => 'success', 'menipis' => 'warning text-dark', 'habis' => 'danger'];
  $allOk = collect($data['reconciliation'])->every(fn($c) => $c['ok']);
@endphp
<div class="d-flex justify-content-between mb-3">
  <h4>Laporan Persediaan</h4>
  <button onclick="window.print()" class="btn btn-outline-secondary no-print">Cetak</button>
</div>
<p class="text-muted">Kondisi per {{ now()->format('d/m/Y H:i') }}</p>

@if($data['low_count'] > 0)
  <div class="alert alert-warning">Peringatan: <b>{{ $data['low_count'] }}</b> barang berstatus menipis atau habis.</div>
@endif

{{-- Ringkasan --}}
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Bahan baku</div>
    <div class="fs-5 fw-bold">Rp {{ $fv($data['raw_value']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Barang dalam proses</div>
    <div class="fs-5 fw-bold">Rp {{ $fv($data['wip_value']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body"><div class="small text-muted">Produk jadi</div>
    <div class="fs-5 fw-bold">Rp {{ $fv($data['fg_value']) }}</div></div></div>
  <div class="col-md-3"><div class="card card-body border-primary"><div class="small text-muted">Total persediaan</div>
    <div class="fs-5 fw-bold">Rp {{ $fv($data['total_value']) }}</div></div></div>
</div>

{{-- Tabel bahan baku dan produk jadi --}}
@foreach([['Bahan Baku', $data['raw'], $data['raw_value']], ['Produk Jadi', $data['fg'], $data['fg_value']]] as [$title, $list, $sum])
<h6>{{ $title }}</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Kode</th><th>Nama</th><th>Satuan</th><th class="num">Stok</th><th class="num">Batas Min.</th>
    <th class="num">Harga Rata-rata</th><th class="num">Nilai</th><th>Status</th></tr></thead>
  <tbody>
  @forelse($list as $i)
    <tr>
      <td>{{ $i->code }}</td><td>{{ $i->name }}</td><td>{{ $i->unit }}</td>
      <td class="num">{{ $fq($i->qty) }}</td>
      <td class="num">{{ $fq($i->min) }}</td>
      <td class="num">{{ $fq($i->cost) }}</td>
      <td class="num">{{ $fv($i->value) }}</td>
      <td><span class="badge bg-{{ $badge[$i->status] }}">{{ $i->status }}</span></td>
    </tr>
  @empty
    <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="6" class="text-end">Total {{ $title }}</th>
    <th class="num">{{ $fv($sum) }}</th><th></th></tr></tfoot>
</table>
@endforeach

{{-- Barang dalam proses --}}
<h6>Barang Dalam Proses</h6>
<table class="table table-bordered table-sm bg-white mb-4">
  <thead><tr><th>Nomor Produksi</th><th>Tanggal Mulai</th><th>Produk</th>
    <th class="num">Jumlah</th><th class="num">Biaya Bahan</th></tr></thead>
  <tbody>
  @forelse($data['wip'] as $p)
    <tr>
      <td><a href="{{ route('productions.show', $p) }}">{{ $p->number }}</a></td>
      <td>{{ \Carbon\Carbon::parse($p->date)->format('d/m/Y') }}</td>
      <td>{{ $p->item->name }}</td>
      <td class="num">{{ $fq($p->qty) }}</td>
      <td class="num">{{ $fv($p->material_cost) }}</td>
    </tr>
  @empty
    <tr><td colspan="5" class="text-center text-muted">Tidak ada produksi yang sedang berjalan.</td></tr>
  @endforelse
  </tbody>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total</th>
    <th class="num">{{ $fv($data['wip_value']) }}</th></tr></tfoot>
</table>
<p class="small text-muted">Barang dalam proses hanya berisi biaya bahan baku. Tenaga kerja dan overhead baru dicatat saat produksi diselesaikan.</p>

{{-- Pencocokan --}}
<h6>Pencocokan dengan Jurnal
  @if($allOk)<span class="badge bg-success">COCOK</span>
  @else<span class="badge bg-danger">ADA SELISIH</span>@endif
</h6>
<table class="table table-bordered table-sm bg-white">
  <thead><tr><th>Persediaan</th><th>Akun Jurnal</th><th class="num">Nilai Stok</th>
    <th class="num">Saldo Jurnal</th><th class="num">Selisih</th><th>Hasil</th></tr></thead>
  <tbody>
  @foreach($data['reconciliation'] as $c)
    <tr>
      <td>{{ $c['label'] }}</td><td>{{ $c['acc'] }}</td>
      <td class="num">{{ $fv($c['stock']) }}</td>
      <td class="num">{{ $fv($c['ledger']) }}</td>
      <td class="num">{{ number_format($c['diff'], 2, ',', '.') }}</td>
      <td><span class="badge bg-{{ $c['ok'] ? 'success' : 'danger' }}">{{ $c['ok'] ? 'cocok' : 'selisih' }}</span></td>
    </tr>
  @endforeach
  </tbody>
</table>
<p class="small text-muted">Selisih di bawah Rp 1 dianggap cocok (akibat pembulatan harga rata-rata). Jika muncul <b>selisih</b>, ada transaksi
  yang mengubah stok tanpa jurnal yang sepadan, atau sebaliknya.</p>
@endsection