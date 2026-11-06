@extends('layouts.sia')
@section('title','Barang')
@section('content')
@php
  $f  = fn($v) => number_format($v, 0, ',', '.');
  $fq = fn($v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
  $statusOf = function ($i) {
      if ($i->stock_qty <= 0) return ['Habis', 'bg-danger-subtle text-danger-emphasis'];
      if ($i->min_stock > 0 && $i->stock_qty <= $i->min_stock) return ['Menipis', 'bg-warning-subtle text-warning-emphasis'];
      return ['Aman', 'bg-success-subtle text-success-emphasis'];
  };
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h4 class="mb-0">Data Master Barang</h4>
    <div class="text-muted">Bahan baku dan produk jadi, terhubung dengan kartu persediaan dan harga pokok</div></div>
  <a href="{{ route('items.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Barang</a>
</div>
@include('partials.master_tabs')

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total barang terdaftar</div>
    <div class="kpi-value">{{ $sum['count'] }}</div><div class="small text-muted">{{ $sum['raw_n'] }} bahan baku | {{ $sum['fg_n'] }} produk jadi</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total nilai persediaan</div>
    <div class="kpi-value">Rp {{ $f($sum['raw'] + $sum['fg']) }}</div><div class="small text-muted">Metode rata-rata tertimbang</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi {{ $sum['low'] ? 'border-danger' : '' }}"><div class="kpi-label">Bahan baku kritis</div>
    <div class="kpi-value {{ $sum['low'] ? 'text-danger' : '' }}">{{ $sum['low'] }} item</div>
    <div class="small text-muted text-truncate">{{ $sum['low_names'] ?: 'Semua aman' }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Kategori terbanyak</div>
    <div class="kpi-value" style="font-size:1.15rem">{{ $sum['top_name'] }}</div><div class="small text-muted">{{ $sum['top_n'] }} barang | Rp {{ $f($sum['top_v']) }}</div></div></div>
</div>

<form class="card card-body mb-3">
  <div class="row g-2">
    <div class="col-lg-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari kode atau nama..."></div>
    <div class="col-lg-2"><select name="type" class="form-select"><option value="">Semua jenis</option>
      <option value="raw_material" @selected(request('type')=='raw_material')>Bahan baku</option>
      <option value="product" @selected(request('type')=='product')>Produk jadi</option></select></div>
    <div class="col-lg-2"><select name="category_id" class="form-select"><option value="">Semua kategori</option>
      @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-lg-2"><select name="stock" class="form-select"><option value="">Semua status stok</option>
      <option value="low" @selected(request('stock')=='low')>Menipis atau habis</option>
      <option value="ok" @selected(request('stock')=='ok')>Aman</option></select></div>
    <div class="col-lg-2"><select name="unit_id" class="form-select"><option value="">Semua satuan</option>
      @foreach($units as $u)<option value="{{ $u->id }}" @selected(request('unit_id')==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-lg-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Kode dan nama</th><th>Kategori</th><th class="num">Harga rata-rata</th><th class="num">Harga jual</th>
          <th class="num">Stok</th><th class="num">Batas min.</th><th>Akun</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $r)
          @php [$sl, $sc] = $statusOf($r); $raw = $r->type == 'raw_material'; @endphp
          <tr>
            <td><div class="d-flex align-items-center gap-2"><span class="avatar">{{ mb_strtoupper(mb_substr($r->name,0,1)) }}</span>
              <div><div class="fw-semibold">{{ $r->name }}</div><div class="small text-muted">{{ $r->code }}</div></div></div></td>
            <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $r->category->name ?? '-' }}</span>
              <div class="small text-muted">{{ $raw ? 'Bahan baku' : 'Produk jadi' }}</div></td>
            <td class="num">Rp {{ $f($r->avg_cost) }}<div class="small text-muted">per {{ $r->unit->name }}</div></td>
            <td class="num">{{ $r->sale_price > 0 ? 'Rp '.$f($r->sale_price) : '-' }}</td>
            <td class="num fw-semibold {{ $sl != 'Aman' && $raw ? 'text-danger' : '' }}">{{ $fq($r->stock_qty) }} {{ $r->unit->name }}</td>
            <td class="num">{{ $fq($r->min_stock) }}</td>
            <td><span class="badge text-bg-light border">{{ $raw ? '1301' : '1303' }}</span></td>
            <td><span class="badge {{ $sc }}">{{ $sl }}</span></td>
            <td class="text-end nowrap">
              @if($raw && $sl != 'Aman')<a href="{{ route('purchases.create') }}" class="btn btn-sm btn-light border" title="Buat pembelian"><i class="bi bi-cart-plus"></i></a>@endif
              <a href="{{ route('inventory.card', ['item_id'=>$r->id]) }}" class="btn btn-sm btn-light border" title="Kartu persediaan"><i class="bi bi-card-list"></i></a>
              <a href="{{ route('items.edit',$r) }}" class="btn btn-sm btn-light border" title="Ubah"><i class="bi bi-pencil"></i></a>
              <form action="{{ route('items.destroy',$r) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus barang ini?')">@csrf @method('DELETE')
                <button class="btn btn-sm btn-light border text-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Tidak ada barang untuk filter ini.</div></div></td></tr>
        @endforelse
        </tbody>
      </table></div>
      <div class="d-flex justify-content-between align-items-center p-3">
        <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} barang</div>
        {{ $rows->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-body h-100">
      <h6>Pemetaan Akun Persediaan</h6>
      <div class="small text-muted mb-2">Perjalanan nilai barang di jurnal</div>
      <table class="table table-sm mb-0"><tbody>
        <tr><td>Beli bahan baku</td><td>D 1301 Persediaan Bahan Baku</td></tr>
        <tr><td>Bahan dipakai produksi</td><td>D 1302 Barang Dalam Proses / K 1301</td></tr>
        <tr><td>Produksi selesai</td><td>D 1303 Barang Jadi / K 1302</td></tr>
        <tr><td>Barang terjual</td><td>D 5101 HPP / K 1303</td></tr>
      </tbody></table>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-body h-100 {{ $restock->count() ? 'border-warning' : '' }}">
      <div class="d-flex justify-content-between"><h6>Aksi Cepat Restock</h6>
        @if($restock->count())<span class="badge bg-danger-subtle text-danger-emphasis">{{ $restock->count() }} butuh order</span>@endif</div>
      <div class="small text-muted mb-2">Bahan baku di bawah batas minimum</div>
      @forelse($restock->take(5) as $i)
        <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
          <div><div class="small fw-semibold">{{ $i->name }}</div>
            <div class="small text-muted">Sisa {{ $fq($i->stock_qty) }} | Min {{ $fq($i->min_stock) }}</div></div>
          <a href="{{ route('purchases.create') }}" class="btn btn-sm btn-primary">Buat Pembelian</a>
        </div>
      @empty
        <div class="text-muted small">Semua stok bahan baku aman.</div>
      @endforelse
      @if($restock->count() > 5)<div class="small text-muted">dan {{ $restock->count() - 5 }} bahan lain. Lihat dengan filter "Menipis atau habis".</div>@endif
    </div>
  </div>
</div>
@endsection