@extends('layouts.sia')
@section('title','Laporan Perubahan Modal')
@section('content')
@php
  $f  = fn($v) => number_format($v, 0, ',', '.');
  $fm = fn($v) => $v < 0 ? '(' . number_format(abs($v), 0, ',', '.') . ')' : number_format($v, 0, ',', '.');
  $cap0 = $data['opening_capital']; $pro0 = $data['opening_profit']; $prv0 = -$data['opening_prive'];
  $capE = $cap0 + $data['capital']; $proE = $pro0 + $data['profit']; $prvE = $prv0 - $data['prive'];
@endphp
<h4>Laporan Keuangan</h4>
@include('reports._nav')
@include('reports._period')

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h5 class="mb-0">Laporan Perubahan Modal</h5>
  @if($data['ok'])<span class="badge bg-success">COCOK DENGAN NERACA</span>
  @else<span class="badge bg-danger">TIDAK COCOK dengan total ekuitas di Neraca</span>@endif
  <span class="text-muted">Periode {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'awal' }} s.d. {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</span>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Modal disetor (akhir)</div><div class="kpi-value">Rp {{ $f($capE) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">{{ $data['profit'] >= 0 ? 'Laba' : 'Rugi' }} periode</div>
    <div class="kpi-value {{ $data['profit'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ $fm($data['profit']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Prive periode</div><div class="kpi-value text-danger">Rp {{ $fm(-$data['prive']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi text-white" style="background:var(--brand)"><div class="kpi-label text-white-50">Modal akhir</div>
    <div class="kpi-value">Rp {{ $fm($data['closing']) }}</div></div></div>
</div>

<div class="card card-body">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr><th>Komponen ekuitas</th><th class="num">Modal disetor</th><th class="num">Laba (rugi) berjalan</th><th class="num">Prive</th><th class="num">Total ekuitas</th></tr></thead>
    <tbody>
      <tr><td class="fw-semibold">Saldo awal periode</td><td class="num">{{ $fm($cap0) }}</td><td class="num">{{ $fm($pro0) }}</td><td class="num">{{ $fm($prv0) }}</td><td class="num fw-semibold">{{ $fm($data['opening']) }}</td></tr>
      <tr><td style="padding-left:2rem">+ Tambahan modal</td><td class="num">{{ $fm($data['capital']) }}</td><td class="num">-</td><td class="num">-</td><td class="num">{{ $fm($data['capital']) }}</td></tr>
      <tr><td style="padding-left:2rem">{{ $data['profit'] >= 0 ? '+ Laba bersih periode' : '- Rugi bersih periode' }}</td><td class="num">-</td><td class="num">{{ $fm($data['profit']) }}</td><td class="num">-</td><td class="num">{{ $fm($data['profit']) }}</td></tr>
      <tr><td style="padding-left:2rem">- Pengambilan prive</td><td class="num">-</td><td class="num">-</td><td class="num">{{ $fm(-$data['prive']) }}</td><td class="num">{{ $fm(-$data['prive']) }}</td></tr>
      <tr class="table-success"><th>Modal akhir periode</th><th class="num">{{ $fm($capE) }}</th><th class="num">{{ $fm($proE) }}</th><th class="num">{{ $fm($prvE) }}</th><th class="num">{{ $fm($data['closing']) }}</th></tr>
    </tbody>
  </table></div>
  <div class="small text-muted mt-2">Belum ada tutup buku, jadi seluruh laba kumulatif tampil di kolom "berjalan" (tidak ada "laba ditahan").
    Modal akhir harus sama dengan Total Ekuitas di Neraca pada tanggal akhir.</div>
</div>
@include('reports._sign')
@endsection