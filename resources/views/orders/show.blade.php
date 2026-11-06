@extends('layouts.sia')
@section('title','Detail Pesanan')
@section('content')
@php
  $role   = auth()->user()->role;
  $canFin = in_array($role, ['admin','keuangan']);
  $badge  = ['menunggu_pembayaran'=>'secondary','dalam_produksi'=>'warning','selesai'=>'info','terkirim'=>'success'][$order->status];
  $sisa   = $order->total - $order->paid_amount - $order->receivable_cut;@endphp
<div class="d-flex justify-content-between mb-3">
  <h4>{{ $order->number }} <span class="badge bg-{{ $badge }}">{{ str_replace('_',' ',$order->status) }}</span></h4>
  <a href="{{ route('orders.index') }}" class="btn btn-secondary">Kembali</a>
</div>

<div class="card card-body mb-3">
  <div class="row">
    <div class="col-md-3"><b>Tanggal:</b> {{ \Carbon\Carbon::parse($order->date)->format('d/m/Y') }}</div>
    <div class="col-md-5"><b>Pelanggan:</b> {{ $order->customer->name }}</div>
    <div class="col-md-4"><b>Jenis:</b> {{ ucfirst($order->payment_type) }}</div>
  </div>
  @if($order->delivered_date)
    <div class="mt-2"><b>Tanggal kirim:</b> {{ \Carbon\Carbon::parse($order->delivered_date)->format('d/m/Y') }}</div>
  @endif
  @if($order->notes)<div class="mt-2"><b>Catatan:</b> {{ $order->notes }}</div>@endif
</div>

<table class="table table-bordered bg-white">
  <thead><tr><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th>
    @if($order->status!='terkirim')<th class="num">Stok Tersedia</th>@endif</tr></thead>
  <tbody>
  @foreach($order->items as $it)
    <tr>
      <td>{{ $it->item->name }}</td>
      <td class="num">{{ number_format($it->qty,2,',','.') }} {{ $it->item->unit->name }}</td>
      <td class="num">{{ number_format($it->unit_price,0,',','.') }}</td>
      <td class="num">{{ number_format($it->subtotal,0,',','.') }}</td>
      @if($order->status!='terkirim')
        <td class="num {{ $it->item->stock_qty < $it->qty ? 'text-danger fw-bold' : '' }}">
          {{ number_format($it->item->stock_qty,2,',','.') }}</td>
      @endif
    </tr>
  @endforeach
  </tbody>
  <tfoot>
    <tr><th colspan="3" class="text-end">Total</th><th class="num">{{ number_format($order->total,0,',','.') }}</th>@if($order->status!='terkirim')<th></th>@endif</tr>
    <tr><th colspan="3" class="text-end">{{ $order->status=='terkirim' ? 'Sudah Dibayar' : 'Uang Muka Diterima' }}</th>
        <th class="num">{{ number_format($order->paid_amount,0,',','.') }}</th>@if($order->status!='terkirim')<th></th>@endif</tr>
    <tr><th colspan="3" class="text-end">{{ $order->status=='terkirim' ? 'Sisa Piutang' : 'Sisa Tagihan' }}</th>
        <th class="num">{{ number_format($sisa,0,',','.') }}</th>@if($order->status!='terkirim')<th></th>@endif</tr>
  </tfoot>
</table>

{{-- Tombol perubahan status --}}
<div class="d-flex gap-2 mb-4">
  @if($order->status=='menunggu_pembayaran')
    <form method="POST" action="{{ route('orders.progress',$order) }}">@csrf
      <input type="hidden" name="to" value="dalam_produksi">
      <button class="btn btn-warning">Mulai Produksi</button></form>
  @endif
  @if($order->status=='dalam_produksi')
    <form method="POST" action="{{ route('orders.progress',$order) }}">@csrf
      <input type="hidden" name="to" value="selesai">
      <button class="btn btn-info">Tandai Selesai</button></form>
  @endif
  @if($canFin && $order->status!='terkirim' && $order->paid_amount == 0)
    <form method="POST" action="{{ route('orders.destroy',$order) }}"
          onsubmit="return confirm('Hapus pesanan ini?')">@csrf @method('DELETE')
      <button class="btn btn-outline-danger">Hapus Pesanan</button></form>
  @endif
</div>
@if($canFin && $order->status=='terkirim')
  <a href="{{ route('sales-returns.create', ['order_id' => $order->id]) }}" class="btn btn-outline-warning mb-4">Buat Retur Barang</a>
@endif

{{-- Pembayaran --}}
@if($canFin && $sisa > 0)
<div class="card card-body mb-4">
  <h6>{{ $order->status=='terkirim' ? 'Terima Pelunasan Piutang' : 'Terima Uang Muka / Pembayaran' }}</h6>
  <form method="POST" action="{{ route('orders.pay',$order) }}" class="row g-2">@csrf
    <div class="col-md-3"><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
    <div class="col-md-3"><input type="number" step="0.01" name="amount" class="form-control" value="{{ $sisa }}" required></div>
    <div class="col-md-3"><select name="account_code" class="form-select">
        <option value="1101">Kas</option><option value="1102">Bank</option></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Catat Pembayaran</button></div>
  </form>
</div>
@endif

{{-- Pengiriman --}}
@if($canFin && $order->status=='selesai')
<div class="card card-body mb-4 border-success">
  <h6>Kirim Barang</h6>
  <p class="small text-muted mb-2">Saat dikirim: stok produk berkurang, pendapatan dan HPP dicatat. Tidak bisa dibatalkan.</p>
  <form method="POST" action="{{ route('orders.deliver',$order) }}" class="row g-2">@csrf
    <div class="col-md-3"><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
    <div class="col-md-3"><button class="btn btn-success w-100"
        onclick="return confirm('Kirim barang dan akui pendapatan?')">Kirim Barang</button></div>
  </form>
</div>
@elseif($order->status=='selesai')
  <div class="alert alert-warning">Pesanan siap dikirim. Hanya admin atau keuangan yang dapat mengirim dan mengakui pendapatan.</div>
@endif

@if($order->payments->count())
<h6>Riwayat Pembayaran</h6>
<table class="table table-sm table-bordered bg-white" style="max-width:600px">
  <thead><tr><th>Tanggal</th><th>Jenis</th><th>Akun</th><th class="num">Jumlah</th></tr></thead>
  <tbody>
  @foreach($order->payments as $p)
    <tr><td>{{ \Carbon\Carbon::parse($p->date)->format('d/m/Y') }}</td>
        <td>{{ $p->type=='dp' ? 'Uang muka' : 'Pelunasan' }}</td>
        <td>{{ $p->account_code=='1101' ? 'Kas' : 'Bank' }}</td>
        <td class="num">{{ number_format($p->amount,0,',','.') }}</td></tr>
  @endforeach
  </tbody>
</table>
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