@extends('layouts.sia')
@section('title','Laporan Arus Kas')
@section('content')
@php
  $f  = fn($v) => number_format($v, 0, ',', '.');
  $fm = fn($v) => $v < 0 ? '(' . number_format(abs($v), 0, ',', '.') . ')' : number_format($v, 0, ',', '.');
  $neg = collect($data['operating'])->merge($data['financing'])->filter(fn($r) => $r['amount'] < 0);
  $negSum = max(abs($neg->sum('amount')), 0.01);
@endphp
<h4>Laporan Keuangan</h4>
@include('reports._nav')
@include('reports._period')

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h5 class="mb-0">Laporan Arus Kas (Metode Langsung)</h5>
  @if($data['ok'])<span class="badge bg-success">COCOK DENGAN SALDO KAS + BANK</span>
  @else<span class="badge bg-danger">TIDAK COCOK dengan saldo Kas + Bank (Rp {{ $f($data['actual']) }})</span>@endif
  <span class="text-muted">Periode {{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'awal' }} s.d. {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</span>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Kas awal periode</div><div class="kpi-value">Rp {{ $f($data['opening']) }}</div></div></div>
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Arus kas masuk</div><div class="kpi-value text-success">+Rp {{ $f($data['inflow']) }}</div></div></div>
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Arus kas keluar</div><div class="kpi-value text-danger">-Rp {{ $f($data['outflow']) }}</div></div></div>
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Kenaikan (penurunan) bersih</div>
    <div class="kpi-value {{ $data['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $data['net'] >= 0 ? '+' : '-' }}Rp {{ $f(abs($data['net'])) }}</div></div></div>
  <div class="col-sm-12 col-xl"><div class="card kpi text-white" style="background:var(--brand)"><div class="kpi-label text-white-50">Kas akhir periode</div>
    <div class="kpi-value">Rp {{ $f($data['closing']) }}</div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card card-body">
      <table class="table mb-0">
        <tbody>
          <tr class="table-light"><th colspan="2">I. Arus kas dari aktivitas operasi</th></tr>
          @forelse($data['operating'] as $r)
            <tr><td style="padding-left:2rem">{{ $r['label'] }}</td><td class="num" width="200">{{ $fm($r['amount']) }}</td></tr>
          @empty
            <tr><td colspan="2" class="text-muted" style="padding-left:2rem">Tidak ada pergerakan kas operasi.</td></tr>
          @endforelse
          <tr><th class="text-end">Kas bersih dari aktivitas operasi</th><th class="num">{{ $fm($data['operating_total']) }}</th></tr>

          <tr class="table-light"><th colspan="2">II. Arus kas dari aktivitas investasi</th></tr>
          <tr><td style="padding-left:2rem" class="text-muted">Belum ada transaksi investasi (sistem belum mencatat aset tetap)</td><td class="num">0</td></tr>
          <tr><th class="text-end">Kas bersih dari aktivitas investasi</th><th class="num">{{ $fm($data['investing_total']) }}</th></tr>

          <tr class="table-light"><th colspan="2">III. Arus kas dari aktivitas pendanaan</th></tr>
          @forelse($data['financing'] as $r)
            <tr><td style="padding-left:2rem">{{ $r['label'] }}</td><td class="num">{{ $fm($r['amount']) }}</td></tr>
          @empty
            <tr><td colspan="2" class="text-muted" style="padding-left:2rem">Tidak ada pergerakan kas pendanaan.</td></tr>
          @endforelse
          <tr><th class="text-end">Kas bersih dari aktivitas pendanaan</th><th class="num">{{ $fm($data['financing_total']) }}</th></tr>

          <tr class="table-info"><th class="text-end">Kenaikan (penurunan) kas bersih</th><th class="num">{{ $fm($data['net']) }}</th></tr>
          <tr><td class="text-end">Saldo kas dan bank awal</td><td class="num">{{ $fm($data['opening']) }}</td></tr>
          <tr class="table-success"><th class="text-end">Saldo kas dan bank akhir</th><th class="num">{{ $fm($data['closing']) }}</th></tr>
        </tbody>
      </table>
      <div class="small text-muted mt-2">Kas mencakup akun Kas dan Bank. Pindah dana antara Kas dan Bank tidak dihitung. Setoran modal dan prive masuk aktivitas pendanaan.</div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card card-body mb-3">
      <h6>Struktur Kas Keluar</h6>
      <div class="small text-muted mb-2">Per jenis transaksi (bersih)</div>
      @forelse($neg as $r)
        @php $p = abs($r['amount']) / $negSum * 100; @endphp
        <div class="d-flex justify-content-between small"><span>{{ $r['label'] }}</span><span><b>Rp {{ $f(abs($r['amount'])) }}</b> ({{ number_format($p,1,',','.') }}%)</span></div>
        <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width:{{ $p }}%;background-color:var(--accent)"></div></div>
      @empty
        <div class="text-muted small">Tidak ada kas keluar.</div>
      @endforelse
    </div>

    <div class="card card-body">
      <h6>Saldo Kas dan Bank per {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</h6>
      <div class="d-flex justify-content-between"><span>Kas (1101)</span><b>Rp {{ $f($bal['1101'] ?? 0) }}</b></div>
      <div class="d-flex justify-content-between"><span>Bank (1102)</span><b>Rp {{ $f($bal['1102'] ?? 0) }}</b></div>
      <hr class="my-2">
      <div class="d-flex justify-content-between"><span>Total</span><b>Rp {{ $f(($bal['1101'] ?? 0) + ($bal['1102'] ?? 0)) }}</b></div>
      <div class="small text-muted mt-2">Menurut jurnal, bukan hasil hitung kas fisik.</div>
    </div>
  </div>
</div>
@include('reports._sign')
@endsection