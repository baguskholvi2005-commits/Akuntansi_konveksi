@extends('layouts.sia')
@section('title','Detail Pembelian')
@section('content')
@php $canPost = in_array(auth()->user()->role, ['admin','keuangan']); @endphp
<div class="d-flex justify-content-between mb-3">
  <h4>{{ $purchase->number }}
    <span class="badge bg-{{ $purchase->status=='confirmed' ? 'success':'secondary' }}">{{ $purchase->status }}</span></h4>
  <a href="{{ route('purchases.index') }}" class="btn btn-secondary">Kembali</a>
</div>

<div class="card card-body mb-3">
  <div class="row">
    <div class="col-md-4"><b>Tanggal:</b> {{ \Carbon\Carbon::parse($purchase->date)->format('d/m/Y') }}</div>
    <div class="col-md-4"><b>Pemasok:</b> {{ $purchase->supplier->name }}</div>
    <div class="col-md-4"><b>Pembayaran:</b> {{ ucfirst($purchase->payment_method) }}</div>
  </div>
</div>

<table class="table table-bordered bg-white">
  <thead><tr><th>Bahan Baku</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
  <tbody>
  @foreach($purchase->items as $it)
    <tr><td>{{ $it->item->name }}</td>
        <td class="num">{{ number_format($it->qty,2,',','.') }} {{ $it->item->unit->name }}</td>
        <td class="num">{{ number_format($it->unit_price,0,',','.') }}</td>
        <td class="num">{{ number_format($it->subtotal,0,',','.') }}</td></tr>
  @endforeach
  </tbody>
  <tfoot>
    <tr><td colspan="3" class="text-end">Subtotal barang</td><td class="num">{{ number_format($purchase->items->sum('subtotal'),0,',','.') }}</td></tr>
    <tr><td colspan="3" class="text-end">Diskon faktur</td><td class="num">({{ number_format($purchase->discount,0,',','.') }})</td></tr>
    <tr><td colspan="3" class="text-end">Ongkos kirim</td><td class="num">{{ number_format($purchase->shipping_cost,0,',','.') }}</td></tr>
    @if($purchase->due_date)<tr><td colspan="3" class="text-end">Jatuh tempo</td><td class="num">{{ \Carbon\Carbon::parse($purchase->due_date)->format('d/m/Y') }}</td></tr>@endif
    <tr><th colspan="3" class="text-end">Total</th><th class="num">{{ number_format($purchase->total,0,',','.') }}</th></tr>
    @if($purchase->status=='confirmed')
    <tr><th colspan="3" class="text-end">Sudah Dibayar</th><th class="num">{{ number_format($purchase->paid_amount,0,',','.') }}</th></tr>
    <tr><th colspan="3" class="text-end">Sisa Utang</th><th class="num">{{ number_format($purchase->total - $purchase->paid_amount,0,',','.') }}</th></tr>
    @endif
  </tfoot>
</table>

@if($purchase->status=='draft')
  <div class="d-flex gap-2 mb-4">
    @if($canPost)
    <form method="POST" action="{{ route('purchases.confirm',$purchase) }}"
          onsubmit="return confirm('Posting pembelian? Setelah diposting tidak bisa diubah.')">@csrf
      <button class="btn btn-success">Konfirmasi dan Posting</button></form>
    @endif
    <form method="POST" action="{{ route('purchases.destroy',$purchase) }}"
          onsubmit="return confirm('Hapus draft ini?')">@csrf @method('DELETE')
      <button class="btn btn-outline-danger">Hapus Draft</button></form>
  </div>
@endif

@if($canPost && $purchase->status=='confirmed' && $purchase->payment_method=='kredit' && $purchase->total > $purchase->paid_amount)
<div class="card card-body mb-4">
  <h6>Bayar Utang</h6>
  <form method="POST" action="{{ route('purchases.pay',$purchase) }}" class="row g-2">@csrf
    <div class="col-md-3"><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
    <div class="col-md-3"><input type="number" step="0.01" name="amount" class="form-control"
         value="{{ $purchase->total - $purchase->paid_amount }}" required></div>
    <div class="col-md-3"><select name="account_code" class="form-select">
        <option value="1101">Kas</option><option value="1102">Bank</option></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Catat Pembayaran</button></div>
  </form>
</div>
@endif

@if($journals->count())
<h6>Jurnal Terkait</h6>
@foreach($journals as $j)
  <table class="table table-sm table-bordered bg-white">
    <thead><tr><th colspan="3">{{ $j->number }} - {{ $j->description }}</th></tr>
      <tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
    <tbody>
    @foreach($j->lines as $l)
      <tr><td>{{ $l->account->code }} {{ $l->account->name }}</td>
          <td class="num">{{ $l->debit>0 ? number_format($l->debit,0,',','.') : '' }}</td>
          <td class="num">{{ $l->credit>0 ? number_format($l->credit,0,',','.') : '' }}</td></tr>
    @endforeach
    </tbody>
  </table>
@endforeach
@endif
@endsection