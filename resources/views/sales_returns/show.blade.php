@extends('layouts.sia')
@section('title','Detail Retur')
@section('content')
@php $f = fn($v) => number_format($v, 0, ',', '.'); @endphp
<div class="d-flex justify-content-between mb-3">
  <h4>{{ $ret->number }}</h4>
  <a href="{{ route('sales-returns.index') }}" class="btn btn-secondary">Kembali</a>
</div>

<div class="card card-body mb-3">
  <div class="row">
    <div class="col-md-3"><b>Tanggal:</b> {{ \Carbon\Carbon::parse($ret->date)->format('d/m/Y') }}</div>
    <div class="col-md-5"><b>Pesanan:</b> <a href="{{ route('orders.show',$ret->order) }}">{{ $ret->order->number }}</a>
      ({{ $ret->order->customer->name }})</div>
    <div class="col-md-4"><b>Catatan:</b> {{ $ret->notes ?: '-' }}</div>
  </div>
</div>

<table class="table table-bordered bg-white">
  <thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Harga Jual</th>
    <th class="num">Nilai Retur</th><th class="num">Harga Pokok</th><th class="num">HPP Dikembalikan</th></tr></thead>
  <tbody>
  @foreach($ret->items as $i)
    <tr><td>{{ $i->item->name }}</td>
      <td class="num">{{ number_format($i->qty,2,',','.') }} {{ $i->item->unit->name }}</td>
      <td class="num">{{ $f($i->unit_price) }}</td><td class="num">{{ $f($i->subtotal) }}</td>
      <td class="num">{{ number_format($i->unit_cost,2,',','.') }}</td><td class="num">{{ $f($i->cost_value) }}</td></tr>
  @endforeach
  </tbody>
  <tfoot>
    <tr><th colspan="3" class="text-end">Total</th><th class="num">{{ $f($ret->total) }}</th><th></th><th class="num">{{ $f($ret->cogs) }}</th></tr>
    <tr><td colspan="3" class="text-end">Memotong piutang</td><td class="num">{{ $f($ret->receivable_cut) }}</td><td colspan="2"></td></tr>
    <tr><td colspan="3" class="text-end">Uang dikembalikan
        {{ $ret->refund_account_code ? '(' . ($ret->refund_account_code=='1101' ? 'Kas' : 'Bank') . ')' : '' }}</td>
        <td class="num">{{ $f($ret->refund_amount) }}</td><td colspan="2"></td></tr>
  </tfoot>
</table>

@if($journals->count())
<h6>Jurnal Terkait</h6>
@foreach($journals as $j)
  <table class="table table-sm table-bordered bg-white">
    <thead><tr><th colspan="3">{{ $j->number }} - {{ $j->description }}</th></tr>
      <tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
    <tbody>
    @foreach($j->lines as $l)
      <tr><td>{{ $l->account->code }} {{ $l->account->name }}</td>
          <td class="num">{{ $l->debit>0 ? $f($l->debit) : '' }}</td>
          <td class="num">{{ $l->credit>0 ? $f($l->credit) : '' }}</td></tr>
    @endforeach
    </tbody>
  </table>
@endforeach
@endif
@endsection