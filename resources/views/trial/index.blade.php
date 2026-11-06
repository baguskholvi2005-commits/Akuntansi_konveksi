@extends('layouts.sia')
@section('title','Neraca Saldo')
@section('content')
@php
  $f = fn($v) => $v > 0.004 ? number_format($v, 0, ',', '.') : '';
  $t = $data['total'];
  $d = fn($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><h4 class="mb-0">Neraca Saldo</h4>
    <div class="text-muted">Saldo awal, mutasi, dan saldo akhir seluruh akun, dihitung dari jurnal</div></div>
  <div class="d-flex gap-2 no-print">@include('partials.export')<button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Cetak</button></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total debit saldo akhir</div><div class="kpi-value">Rp {{ number_format($t['cd'],0,',','.') }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total kredit saldo akhir</div><div class="kpi-value">Rp {{ number_format($t['ck'],0,',','.') }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Selisih</div>
    <div class="kpi-value {{ $data['balanced'] ? 'text-success' : 'text-danger' }}">Rp {{ number_format(abs($t['cd'] - $t['ck']),0,',','.') }}</div>
    <span class="badge {{ $data['balanced'] ? 'bg-success' : 'bg-danger' }}">{{ $data['balanced'] ? 'SEIMBANG' : 'TIDAK SEIMBANG, periksa jurnal' }}</span></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Akun bertransaksi</div><div class="kpi-value">{{ count($data['rows']) }}</div></div></div>
</div>

<form class="card card-body mb-3 no-print">
  <div class="row g-2 align-items-end">
    <div class="col-lg-3"><label class="form-label small mb-0">Cari akun</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Kode atau nama"></div>
    <div class="col-lg-2"><label class="form-label small mb-0">Dari tanggal</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
    <div class="col-lg-2"><label class="form-label small mb-0">Sampai tanggal</label><input type="date" name="as_of" value="{{ $asOf }}" class="form-control"></div>
    <div class="col-lg-3"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="zero" value="1" id="z" {{ request('zero') ? 'checked' : '' }}>
      <label class="form-check-label" for="z">Tampilkan saldo nol</label></div></div>
    <div class="col-lg-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
  </div>
</form>

<div class="card mb-3">
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead>
      <tr><th rowspan="2" width="80">Kode</th><th rowspan="2">Nama akun</th><th rowspan="2">Jenis</th>
        <th colspan="2" class="text-center">Saldo awal {{ $from ? '('.$d($from).')' : '' }}</th><th colspan="2" class="text-center">Mutasi periode</th><th colspan="2" class="text-center">Saldo akhir ({{ $d($asOf) }})</th></tr>
      <tr><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Debit</th><th class="num">Kredit</th></tr>
    </thead>
    <tbody>
    @forelse($rows as $r)
      <tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $r['type'] }}</span></td>
        <td class="num">{{ $f($r['od']) }}</td><td class="num">{{ $f($r['ok']) }}</td>
        <td class="num">{{ $f($r['md']) }}</td><td class="num">{{ $f($r['mk']) }}</td>
        <td class="num fw-semibold">{{ $f($r['cd']) }}</td><td class="num fw-semibold">{{ $f($r['ck']) }}</td></tr>
    @empty
      <tr><td colspan="9"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Tidak ada akun yang cocok.</div></div></td></tr>
    @endforelse
    </tbody>
    <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total seluruh akun</th>
      <th class="num">{{ number_format($t['od'],0,',','.') }}</th><th class="num">{{ number_format($t['ok'],0,',','.') }}</th>
      <th class="num">{{ number_format($t['md'],0,',','.') }}</th><th class="num">{{ number_format($t['mk'],0,',','.') }}</th>
      <th class="num">{{ number_format($t['cd'],0,',','.') }}</th><th class="num">{{ number_format($t['ck'],0,',','.') }}</th></tr></tfoot>
  </table></div>
</div>

<div class="card card-body">
  <h6>Struktur Saldo per Jenis Akun</h6>
  <div class="row g-2">
    @foreach(['aset'=>'Aset','liabilitas'=>'Liabilitas','ekuitas'=>'Ekuitas','pendapatan'=>'Pendapatan','hpp'=>'HPP','beban'=>'Beban'] as $k => $lbl)
      <div class="col-6 col-md-4 col-xl-2"><div class="small text-muted">{{ $lbl }}</div>
        <div class="fw-semibold">Rp {{ number_format(abs($byType[$k] ?? 0),0,',','.') }}</div>
        <div class="small text-muted">{{ ($byType[$k] ?? 0) >= 0 ? 'debit' : 'kredit' }}</div></div>
    @endforeach
  </div>
</div>
@endsection