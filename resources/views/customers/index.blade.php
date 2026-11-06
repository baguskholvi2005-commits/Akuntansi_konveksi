@extends('layouts.sia')
@section('title','Pelanggan')
@section('content')
@php $f = fn($v) => number_format($v, 0, ',', '.'); $ag = array_sum($aging); @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h4 class="mb-0">Data Master</h4><div class="text-muted">Pelanggan, riwayat pesanan, dan piutang usaha</div></div>
  <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Pelanggan</a>
</div>
@include('partials.master_tabs')

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total pelanggan</div><div class="kpi-value">{{ $sum['count'] }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Piutang usaha</div><div class="kpi-value text-danger">Rp {{ $f($sum['recv']) }}</div>
    <div class="small text-muted">{{ $sum['recv_n'] }} pesanan belum lunas</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Rata-rata nilai pesanan</div><div class="kpi-value">Rp {{ $f($sum['avg']) }}</div><div class="small text-muted">Pesanan terkirim</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Pelanggan dengan pesanan aktif</div><div class="kpi-value">{{ $sum['active'] }}</div></div></div>
</div>

<form class="card card-body mb-3">
  <div class="row g-2">
    <div class="col-lg-6"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari kode, nama, atau telepon..."></div>
    <div class="col-lg-4"><select name="recv" class="form-select"><option value="">Semua status piutang</option>
      <option value="yes" @selected(request('recv')=='yes')>Punya piutang</option><option value="no" @selected(request('recv')=='no')>Tanpa piutang</option></select></div>
    <div class="col-lg-2"><button class="btn btn-primary w-100">Terapkan</button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Pelanggan</th><th>Kontak</th><th class="num">Pesanan</th><th class="num">Penjualan terkirim</th><th class="num">Saldo piutang</th><th width="150"></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        @php $left = max(round((float) $r->recv_total - (float) $r->recv_paid - (float) $r->recv_cut, 2), 0); @endphp
        <tr>
          <td><div class="d-flex align-items-center gap-2"><span class="avatar">{{ mb_strtoupper(mb_substr($r->name,0,1)) }}</span>
            <div><div class="fw-semibold">{{ $r->name }}</div><div class="small text-muted">{{ $r->code }}</div></div></div></td>
          <td class="small">{{ $r->phone ?: '-' }}<div class="text-muted">{{ $r->address }}</div></td>
          <td class="num">{{ $r->orders_count }}@if($r->active_count)<div class="small text-muted">{{ $r->active_count }} aktif</div>@endif</td>
          <td class="num">Rp {{ $f($r->sold_total) }}</td>
          <td class="num">@if($left > 0)<span class="badge bg-danger-subtle text-danger-emphasis">Rp {{ $f($left) }}</span>@else<span class="badge bg-success-subtle text-success-emphasis">Lunas</span>@endif</td>
          <td class="text-end nowrap">
            <a href="{{ route('customers.edit',$r) }}" class="btn btn-sm btn-light border" title="Ubah"><i class="bi bi-pencil"></i></a>
            <form action="{{ route('customers.destroy',$r) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pelanggan ini?')">@csrf @method('DELETE')
              <button class="btn btn-sm btn-light border text-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Belum ada pelanggan.</div></div></td></tr>
      @endforelse
      </tbody>
    </table></div>
    <div class="d-flex justify-content-between align-items-center p-3">
      <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} pelanggan</div>
      {{ $rows->links('pagination::bootstrap-5') }}
    </div>
  </div></div>

  <div class="col-xl-4"><div class="card card-body">
    <h6>Umur Piutang (Aging)</h6>
    <div class="small text-muted mb-2">Sejak tanggal kirim</div>
    @foreach(['0-15' => '0 - 15 hari', '16-30' => '16 - 30 hari', '>30' => 'Lebih dari 30 hari'] as $k => $lbl)
      <div class="d-flex justify-content-between small"><span>{{ $lbl }}</span><b class="{{ $k == '>30' && $aging[$k] > 0 ? 'text-danger' : '' }}">Rp {{ $f($aging[$k]) }}</b></div>
      <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width:{{ $ag > 0 ? $aging[$k] / $ag * 100 : 0 }}%;background-color:var(--accent)"></div></div>
    @endforeach
    <a href="{{ route('reports.debts') }}" class="small">Lihat laporan piutang lengkap</a>
  </div></div>
</div>
@endsection