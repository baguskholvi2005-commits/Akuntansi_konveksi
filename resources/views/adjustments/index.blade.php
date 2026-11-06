@extends('layouts.sia')
@section('title','Penyesuaian Stok')
@section('content')
<div class="d-flex justify-content-between mb-3">
  <h4>Penyesuaian Stok</h4>
  <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary">+ Hasil Hitung Fisik</a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor..."></div>
  <div class="col-md-2"><select name="status" class="form-select">
    <option value="">Semua status</option>
    <option value="draft" @selected(request('status')=='draft')>Menunggu persetujuan</option>
    <option value="posted" @selected(request('status')=='posted')>Disetujui</option>
  </select></div>
  <div class="col-md-3"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
  <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<table class="table table-bordered bg-white">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Barang</th><th class="num">Hasil Hitung</th>
    <th class="num">Selisih</th><th class="num">Nilai</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @forelse($rows as $r)
    <tr>
      <td>{{ $r->number }}</td>
      <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
      <td>{{ $r->item->name }}</td>
      <td class="num">{{ number_format($r->counted_qty,2,',','.') }} {{ $r->item->unit->name }}</td>
      <td class="num {{ $r->diff_qty < 0 ? 'text-danger' : '' }}">{{ $r->diff_qty !== null ? number_format($r->diff_qty,2,',','.') : '-' }}</td>
      <td class="num">{{ $r->status=='posted' ? number_format($r->value,0,',','.') : '-' }}</td>
      <td><span class="badge bg-{{ $r->status=='posted' ? 'success' : 'secondary' }}">{{ $r->status=='posted' ? 'disetujui' : 'menunggu' }}</span></td>
      <td><a href="{{ route('stock-adjustments.show',$r) }}" class="btn btn-sm btn-info">Detail</a></td>
    </tr>
  @empty
    <tr><td colspan="8" class="text-center text-muted">Belum ada penyesuaian.</td></tr>
  @endforelse
  </tbody>
</table>
{{ $rows->links('pagination::bootstrap-5') }}
@endsection