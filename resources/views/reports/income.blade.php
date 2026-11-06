@extends('layouts.sia')
@section('title','Laporan Laba Rugi')
@section('content')
@php $f = fn($v) => number_format($v, 0, ',', '.'); @endphp
<h4>Laporan Keuangan</h4>
@include('reports._nav')
@include('reports._period')

<div class="text-center mb-3">
  <h5 class="mb-0">{{ \App\Models\Setting::val('company_name') }}</h5>
  <h5 class="mb-0">Laporan Laba Rugi</h5>
  <div class="text-muted">Periode {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'awal' }}
    s.d. {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

<table class="table table-bordered bg-white" style="max-width:800px;margin:auto">
  <tbody>
    <tr class="table-light"><th colspan="2">Pendapatan</th></tr>
    @forelse($data['revenue'] as $r)
      <tr><td style="padding-left:2rem">{{ $r['code'] }} {{ $r['name'] }}</td><td class="num" width="200">{{ $f($r['amount']) }}</td></tr>
    @empty
      <tr><td colspan="2" class="text-muted" style="padding-left:2rem">Tidak ada pendapatan pada periode ini.</td></tr>
    @endforelse
    <tr><th class="text-end">Total Pendapatan</th><th class="num">{{ $f($data['revenue_total']) }}</th></tr>

    <tr class="table-light"><th colspan="2">Harga Pokok Penjualan</th></tr>
    @forelse($data['hpp'] as $r)
      <tr><td style="padding-left:2rem">{{ $r['code'] }} {{ $r['name'] }}</td><td class="num">{{ $f($r['amount']) }}</td></tr>
    @empty
      <tr><td colspan="2" class="text-muted" style="padding-left:2rem">Tidak ada HPP pada periode ini.</td></tr>
    @endforelse
    <tr><th class="text-end">Total HPP</th><th class="num">{{ $f($data['hpp_total']) }}</th></tr>

    <tr class="table-info"><th class="text-end">LABA KOTOR</th><th class="num">{{ $f($data['gross']) }}</th></tr>

    <tr class="table-light"><th colspan="2">Beban Operasional</th></tr>
    @forelse($data['expenses'] as $r)
      <tr><td style="padding-left:2rem">{{ $r['code'] }} {{ $r['name'] }}</td><td class="num">{{ $f($r['amount']) }}</td></tr>
    @empty
      <tr><td colspan="2" class="text-muted" style="padding-left:2rem">Tidak ada beban pada periode ini.</td></tr>
    @endforelse
    <tr><th class="text-end">Total Beban Operasional</th><th class="num">{{ $f($data['expense_total']) }}</th></tr>

    <tr class="{{ $data['net'] >= 0 ? 'table-success' : 'table-danger' }}">
      <th class="text-end">{{ $data['net'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</th>
      <th class="num">{{ $f($data['net']) }}</th></tr>
  </tbody>
</table>
<p class="small text-muted text-center mt-2">Akun yang pernah bertransaksi tetap tampil walau hasil bersihnya 0 (misalnya beban yang sudah dibatalkan).
  Angka negatif berarti mengurangi.</p>
@endsection