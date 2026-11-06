@extends('layouts.sia')
@section('title','Retur Penjualan')
@section('content')
<div class="d-flex justify-content-between mb-3">
  <h4>Retur Penjualan</h4>
  <a href="{{ route('sales-returns.create') }}" class="btn btn-primary">+ Retur Baru</a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor..."></div>
  <div class="col-md-3"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
  <div class="col-md-3"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<table class="table table-bordered bg-white">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pesanan</th><th>Pelanggan</th>
    <th class="num">Nilai Retur</th><th class="num">Potong Piutang</th><th class="num">Uang Dikembalikan</th><th></th></tr></thead>
  <tbody>
  @forelse($rows as $r)
    <tr>
      <td>{{ $r->number }}</td>
      <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
      <td><a href="{{ route('orders.show',$r->order) }}">{{ $r->order->number }}</a></td>
      <td>{{ $r->order->customer->name }}</td>
      <td class="num">{{ number_format($r->total,0,',','.') }}</td>
      <td class="num">{{ number_format($r->receivable_cut,0,',','.') }}</td>
      <td class="num">{{ number_format($r->refund_amount,0,',','.') }}</td>
      <td><a href="{{ route('sales-returns.show',$r) }}" class="btn btn-sm btn-info">Detail</a></td>
    </tr>
  @empty
    <tr><td colspan="8" class="text-center text-muted">Belum ada retur.</td></tr>
  @endforelse
  </tbody>
</table>
{{ $rows->links('pagination::bootstrap-5') }}
@endsection