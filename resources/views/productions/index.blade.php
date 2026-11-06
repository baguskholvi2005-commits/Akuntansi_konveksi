@extends('layouts.sia')
@section('title','Produksi')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $tabs = ['all' => 'Semua', 'draft' => 'Draft'] + \App\Models\Production::STAGES + ['selesai' => 'Selesai'];
  $badge = ['draft' => 'bg-secondary-subtle text-secondary-emphasis', 'proses' => 'bg-warning-subtle text-warning-emphasis', 'selesai' => 'bg-success-subtle text-success-emphasis'];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h4 class="mb-0">Manajemen Produksi</h4>
    <div class="text-muted">Pantau tahapan potong, sablon/bordir, jahit, QC, dan harga pokok per batch</div></div>
  <a href="{{ route('productions.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Buat Produksi Baru</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total produksi berjalan</div>
    <div class="kpi-value">{{ $stats['running'] }} batch</div><div class="small text-muted">{{ $f($stats['run_qty']) }} pcs dalam proses</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Tahap potong dan sablon</div>
    <div class="kpi-value">{{ $stats['early'] }} batch</div><div class="small text-muted">{{ $f($stats['early_qty']) }} pcs</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Tahap jahit dan QC</div>
    <div class="kpi-value">{{ $stats['late'] }} batch</div><div class="small text-muted">{{ $f($stats['late_qty']) }} pcs</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Selesai bulan ini</div>
    <div class="kpi-value">{{ $stats['done'] }} batch</div><div class="small text-muted">{{ $stats['drafts'] }} draft belum dimulai</div></div></div>
</div>

<div class="card card-body mb-3">
  <ul class="nav nav-tabs mb-3">
    @foreach($tabs as $k => $label)
      <li class="nav-item"><a class="nav-link {{ $tab==$k ? 'active' : '' }}"
        href="{{ route('productions.index', array_merge(request()->except('tab','page'), ['tab'=>$k])) }}">{{ $label }} <span class="text-muted">({{ $counts[$k] }})</span></a></li>
    @endforeach
  </ul>
  <form class="row g-2">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor atau nama produk..."></div>
    <div class="col-md-3"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-md-3"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr><th>No. Produksi</th><th>Tanggal</th><th>Produk</th><th class="num">Jumlah</th><th style="min-width:170px">Tahap dan progres</th>
      <th class="num">HPP / unit</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($rows as $r)
      <tr>
        <td class="fw-semibold nowrap">{{ $r->number }}</td>
        <td class="nowrap">{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
        <td>{{ $r->item->name }}</td>
        <td class="num">{{ number_format($r->qty,0,',','.') }} {{ $r->item->unit->name }}</td>
        <td>
          <div class="small mb-1">{{ $r->status=='selesai' ? 'Selesai' : ($r->status=='draft' ? 'Belum dimulai' : \App\Models\Production::STAGES[$r->stage]) }}</div>
          <div class="progress" style="height:6px"><div class="progress-bar {{ $r->status=='selesai' ? 'bg-success' : '' }}" style="width:{{ $r->progress }}%;background-color:{{ $r->status=='selesai' ? '' : 'var(--accent)' }}"></div></div>
        </td>
        <td class="num">{{ $r->status=='selesai' ? 'Rp '.$f($r->unit_cost) : '-' }}</td>
        <td><span class="badge {{ $badge[$r->status] }}">{{ $r->status }}</span></td>
        <td class="text-end"><a href="{{ route('productions.show',$r) }}" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i></a></td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Belum ada produksi pada tampilan ini.</div></div></td></tr>
    @endforelse
    </tbody>
  </table></div>
  <div class="d-flex justify-content-between align-items-center p-3">
    <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} batch</div>
    {{ $rows->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection