@extends('layouts.sia')
@section('title','Pemasok')
@section('content')
@php $f = fn($v) => number_format($v, 0, ',', '.'); @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h4 class="mb-0">Data Master</h4><div class="text-muted">Pemasok bahan baku dan utang usaha ke tiap pemasok</div></div>
  <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Pemasok</a>
</div>
@include('partials.master_tabs')

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total pemasok</div><div class="kpi-value">{{ $sum['count'] }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Utang ke pemasok</div><div class="kpi-value text-danger">Rp {{ $f($sum['debt']) }}</div>
    <div class="small text-muted">Pembelian kredit belum lunas</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Pemasok berutang</div><div class="kpi-value">{{ $sum['debtors'] }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Pembelian bulan ini</div><div class="kpi-value">Rp {{ $f($sum['month']) }}</div></div></div>
</div>

<form class="card card-body mb-3">
  <div class="row g-2">
    <div class="col-lg-6"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari kode, nama, atau telepon..."></div>
    <div class="col-lg-4"><select name="debt" class="form-select"><option value="">Semua status utang</option>
      <option value="yes" @selected(request('debt')=='yes')>Masih berutang</option><option value="no" @selected(request('debt')=='no')>Tidak berutang</option></select></div>
    <div class="col-lg-2"><button class="btn btn-primary w-100">Terapkan</button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Pemasok</th><th>Kontak</th><th class="num">Transaksi</th><th class="num">Total pembelian</th><th class="num">Saldo utang</th><th width="150"></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php $left = max(round((float) $r->credit_total - (float) $r->credit_paid, 2), 0); @endphp
        <tr>
          <td><div class="d-flex align-items-center gap-2"><span class="avatar">{{ mb_strtoupper(mb_substr($r->name,0,1)) }}</span>
            <div><div class="fw-semibold">{{ $r->name }}</div><div class="small text-muted">{{ $r->code }}</div></div></div></td>
          <td class="small">{{ $r->phone ?: '-' }}<div class="text-muted">{{ $r->address }}</div></td>
          <td class="num">{{ $r->trx_count }}</td>
          <td class="num">Rp {{ $f($r->buy_total) }}</td>
          <td class="num">@if($left > 0)<span class="badge bg-danger-subtle text-danger-emphasis">Rp {{ $f($left) }}</span>@else<span class="badge bg-success-subtle text-success-emphasis">Lunas</span>@endif</td>
          <td class="text-end nowrap">
            <a href="{{ route('suppliers.edit',$r) }}" class="btn btn-sm btn-light border" title="Ubah"><i class="bi bi-pencil"></i></a>
            <form action="{{ route('suppliers.destroy',$r) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pemasok ini?')">@csrf @method('DELETE')
              <button class="btn btn-sm btn-light border text-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Belum ada pemasok.</div></div></td></tr>
      @endforelse
      </tbody>
    </table></div>
    <div class="d-flex justify-content-between align-items-center p-3">
      <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} pemasok</div>
      {{ $rows->links('pagination::bootstrap-5') }}
    </div>
  </div></div>

  <div class="col-xl-4"><div class="card card-body">
    <h6>Utang Terbesar</h6>
    <div class="small text-muted mb-2">Sisa utang per pemasok (sama dengan laporan Utang dan Piutang)</div>
    @forelse($byS->take(5) as $s)
      <div class="d-flex justify-content-between small py-1"><span>{{ $s['name'] }}</span><b>Rp {{ $f($s['left']) }}</b></div>
      <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width:{{ $sum['debt'] > 0 ? $s['left'] / $sum['debt'] * 100 : 0 }}%;background-color:var(--accent)"></div></div>
    @empty
      <div class="text-muted small">Tidak ada utang ke pemasok.</div>
    @endforelse
    <a href="{{ route('reports.debts') }}" class="small">Lihat laporan utang lengkap</a>
  </div></div>
</div>
@endsection