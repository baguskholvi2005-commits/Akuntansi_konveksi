@extends('layouts.sia')
@section('title','Buku Besar')
@section('content')
@php
  $f = fn($v) => number_format(abs($v), 0, ',', '.');
  if ($acc) {
    $dc = fn($v) => (($v >= 0) === ($acc->normal_balance === 'debit')) ? 'D' : 'K';
    $tot = max($data['total_debit'] + $data['total_credit'], 0.01);
  }
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><h4 class="mb-0">Buku Besar</h4>
    <div class="text-muted">Mutasi debit, kredit, dan saldo berjalan per akun, dihitung dari jurnal umum</div></div>
  @if($data)
  <div class="d-flex gap-2 no-print">@include('partials.export')<button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Cetak</button></div>
  @endif
</div>

<form class="card card-body mb-3 no-print">
  <div class="row g-2">
    <div class="col-lg-6"><label class="form-label small mb-0">Akun</label>
      <select name="account_id" class="form-select" required>
        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account_id')==$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach
      </select></div>
    <div class="col-lg-2"><label class="form-label small mb-0">Dari tanggal</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-lg-2"><label class="form-label small mb-0">Sampai tanggal</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tampilkan</button></div>
  </div>
</form>

@if($data)
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Saldo awal</div>
    <div class="kpi-value">Rp {{ $f($data['opening']) }} <small class="fs-6 text-muted">{{ $dc($data['opening']) }}</small></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total mutasi debit</div>
    <div class="kpi-value text-success">Rp {{ $f($data['total_debit']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total mutasi kredit</div>
    <div class="kpi-value text-danger">Rp {{ $f($data['total_credit']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi text-white" style="background:var(--brand)"><div class="kpi-label text-white-50">Saldo akhir</div>
    <div class="kpi-value">Rp {{ $f($data['closing']) }} <small class="fs-6">{{ $dc($data['closing']) }}</small></div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-9">
    <div class="card mb-3">
      <div class="card-body pb-0">
        <h6 class="mb-0">{{ $acc->code }} - {{ $acc->name }}</h6>
        <div class="small text-muted mb-2">Jenis: {{ $acc->type }} | Saldo normal: {{ $acc->normal_balance }} |
          Periode {{ request('from') ? \Carbon\Carbon::parse(request('from'))->format('d/m/Y') : 'awal' }} s.d. {{ request('to') ? \Carbon\Carbon::parse(request('to'))->format('d/m/Y') : 'sekarang' }}</div>
      </div>
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Tanggal</th><th>No. Jurnal</th><th>Keterangan dan lawan akun</th><th>Sumber</th>
          <th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr></thead>
        <tbody>
          <tr class="table-light"><td colspan="6"><b>Saldo awal</b></td><td class="num"><b>{{ $f($data['opening']) }} {{ $dc($data['opening']) }}</b></td></tr>
        @forelse($data['rows'] as $row)
          <tr>
            <td class="nowrap">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
            <td class="nowrap"><a href="{{ route('journals.show',$row['journal_id']) }}" class="text-decoration-none">{{ $row['number'] }}</a></td>
            <td>{{ $row['description'] }}@if($row['counter'])<div class="small text-muted">Lawan: {{ $row['counter'] }}</div>@endif</td>
            <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $row['source_type'] ? (\App\Models\Journal::SOURCES[$row['source_type']] ?? class_basename($row['source_type'])) : 'Manual' }}</span></td>
            <td class="num text-success">{{ $row['debit'] > 0 ? $f($row['debit']) : '' }}</td>
            <td class="num text-danger">{{ $row['credit'] > 0 ? $f($row['credit']) : '' }}</td>
            <td class="num fw-semibold">{{ $f($row['balance']) }} {{ $dc($row['balance']) }}</td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Tidak ada mutasi pada periode ini.</div></div></td></tr>
        @endforelse
        </tbody>
        <tfoot>
          <tr class="table-light"><th colspan="4" class="text-end">Total mutasi</th>
            <th class="num">{{ $f($data['total_debit']) }}</th><th class="num">{{ $f($data['total_credit']) }}</th><th></th></tr>
          <tr class="table-light"><th colspan="6" class="text-end">Saldo akhir</th><th class="num">{{ $f($data['closing']) }} {{ $dc($data['closing']) }}</th></tr>
        </tfoot>
      </table></div>
    </div>

    <div class="card card-body mb-3">
      <div class="d-flex flex-wrap align-items-center gap-2">
        @if($recon['ok'])<span class="badge bg-success">COCOK DENGAN NERACA SALDO</span>
        @else<span class="badge bg-danger">SELISIH DENGAN NERACA SALDO</span>@endif
        <span class="small">Saldo akhir buku besar Rp {{ $f($data['closing']) }}; menurut Neraca Saldo Rp {{ $f($recon['tb']) }}.</span>
      </div>
    </div>

    <div class="card card-body">
      <h6>Proporsi Mutasi</h6>
      <div class="progress" style="height:14px">
        <div class="progress-bar bg-success" style="width:{{ $data['total_debit'] / $tot * 100 }}%"></div>
        <div class="progress-bar bg-danger" style="width:{{ $data['total_credit'] / $tot * 100 }}%"></div>
      </div>
      <div class="d-flex justify-content-between small mt-1">
        <span>Debit Rp {{ $f($data['total_debit']) }} ({{ number_format($data['total_debit'] / $tot * 100, 0) }}%)</span>
        <span>Kredit Rp {{ $f($data['total_credit']) }} ({{ number_format($data['total_credit'] / $tot * 100, 0) }}%)</span></div>
    </div>
  </div>

  <div class="col-xl-3">
    <div class="card card-body mb-3">
      <h6>Ringkasan Akun</h6>
      <div class="small text-muted mb-2">Saldo per {{ request('to') ? \Carbon\Carbon::parse(request('to'))->format('d/m/Y') : 'hari ini' }}. Klik untuk membuka.</div>
      <div style="max-height:360px;overflow:auto">
      @foreach($side as $s)
        <a href="{{ route('ledger.index', ['account_id'=>$s['id'], 'from'=>request('from'), 'to'=>request('to')]) }}"
           class="d-flex justify-content-between text-decoration-none py-1 px-2 rounded {{ $s['id'] == $acc->id ? 'bg-light fw-semibold' : '' }}">
          <span class="small">{{ $s['code'] }} {{ $s['name'] }}</span><span class="small nowrap">{{ $f($s['amount']) }} {{ $s['dc'] }}</span></a>
      @endforeach
      </div>
    </div>

    <div class="card card-body">
      <h6>Pemeriksaan</h6>
      <div class="d-flex justify-content-between"><span>Neraca Saldo</span>
        <span class="badge {{ $recon['balanced'] ? 'bg-success' : 'bg-danger' }}">{{ $recon['balanced'] ? 'Seimbang' : 'Tidak seimbang' }}</span></div>
      <a href="{{ route('trial.index', ['as_of' => request('to')]) }}" class="btn btn-outline-primary btn-sm mt-3">Buka Neraca Saldo</a>
    </div>
  </div>
</div>
@endif
@endsection