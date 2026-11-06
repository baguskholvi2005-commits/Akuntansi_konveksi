@extends('layouts.sia')
@section('title','Jurnal Umum')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $canPost = in_array(auth()->user()->role, ['admin','keuangan']);
  $diff = round($stats['debit'] - $stats['credit'], 2);
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><h4 class="mb-0">Jurnal Umum Transaksi</h4>
    <div class="text-muted">Catatan kronologis seluruh entri debit dan kredit dari transaksi dan jurnal manual</div></div>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Cetak</button>
    @if($canPost)<a href="{{ route('journals.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Jurnal Manual</a>@endif
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total debit (sesuai filter)</div><div class="kpi-value">Rp {{ $f($stats['debit']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total kredit (sesuai filter)</div><div class="kpi-value">Rp {{ $f($stats['credit']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Selisih debit - kredit</div>
    <div class="kpi-value {{ abs($diff) < 0.01 ? 'text-success' : 'text-danger' }}">Rp {{ $f($diff) }}</div>
    <span class="badge {{ abs($diff) < 0.01 ? 'bg-success' : 'bg-danger' }}">{{ abs($diff) < 0.01 ? 'SEIMBANG' : 'TIDAK SEIMBANG' }}</span></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Jumlah entri jurnal</div>
    <div class="kpi-value">{{ $stats['count'] }}</div>
    <div class="small text-muted">{{ $stats['count'] - $stats['manual'] }} otomatis | {{ $stats['manual'] }} manual</div></div></div>
</div>

<form class="card card-body mb-3 no-print">
  <div class="row g-2">
    <div class="col-lg-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor atau keterangan..."></div>
    <div class="col-lg-3"><select name="account_id" class="form-select"><option value="">Semua akun</option>
      @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account_id')==$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
    <div class="col-lg-2"><select name="source" class="form-select"><option value="">Semua sumber</option>
      <option value="manual" @selected(request('source')=='manual')>Jurnal manual</option>
      @foreach(\App\Models\Journal::SOURCES as $class => $label)<option value="{{ $class }}" @selected(request('source')==$class)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-lg-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-lg-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  </div>
  <div class="mt-2"><button class="btn btn-primary btn-sm">Terapkan</button>
    <a href="{{ route('journals.index') }}" class="btn btn-light border btn-sm">Reset</a></div>
</form>

<div class="card">
  <div class="card-body pb-0"><h6 class="mb-2">Daftar Jurnal Terposting <span class="badge bg-info-subtle text-info-emphasis">{{ $rows->total() }} record</span></h6></div>
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr><th>Tanggal dan No. Jurnal</th><th>Sumber</th><th>Keterangan dan Akun</th><th class="num">Debit</th><th class="num">Kredit</th><th>Status</th></tr></thead>
    @forelse($rows as $j)
      @php
        $status = $j->is_reversal ? ['Pembalik','bg-warning-subtle text-warning-emphasis'] : ($j->source_type ? ['Otomatis','bg-success-subtle text-success-emphasis'] : ['Manual','bg-primary-subtle text-primary-emphasis']);
      @endphp
      <tbody class="border-top">
        <tr class="table-light">
          <td class="nowrap"><b>{{ \Carbon\Carbon::parse($j->date)->format('d/m/Y') }}</b>
            <div class="small"><a href="{{ route('journals.show',$j) }}" class="text-decoration-none">{{ $j->number }}</a> <span class="text-muted">{{ $j->created_at?->format('H:i') }}</span></div></td>
          <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $j->source_label }}</span></td>
          <td colspan="3" class="fw-semibold">{{ $j->description }}</td>
          <td><span class="badge {{ $status[1] }}">{{ $status[0] }}</span></td>
        </tr>
        @foreach($j->lines as $l)
          <tr>
            <td></td><td></td>
            <td style="{{ $l->credit > 0 ? 'padding-left:2.5rem' : 'padding-left:1rem' }}">{{ $l->account->code }} - {{ $l->account->name }}@if($l->memo)<div class="small text-muted">{{ $l->memo }}</div>@endif</td>
            <td class="num">{{ $l->debit > 0 ? $f($l->debit) : '' }}</td>
            <td class="num">{{ $l->credit > 0 ? $f($l->credit) : '' }}</td>
            <td></td>
          </tr>
        @endforeach
      </tbody>
    @empty
      <tbody><tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Belum ada jurnal untuk filter ini.</div></div></td></tr></tbody>
    @endforelse
    <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total sesuai filter {{ abs($diff) < 0.01 ? '(SEIMBANG)' : '(TIDAK SEIMBANG)' }}</th>
      <th class="num">{{ $f($stats['debit']) }}</th><th class="num">{{ $f($stats['credit']) }}</th><th></th></tr></tfoot>
  </table></div>
  <div class="d-flex justify-content-between align-items-center p-3">
    <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} jurnal</div>
    {{ $rows->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection