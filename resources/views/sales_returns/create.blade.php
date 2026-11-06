@extends('layouts.sia')
@section('title','Retur Penjualan Baru')
@section('content')
<h4>Retur Penjualan Baru</h4>

<form class="row g-2 mb-3">
  <div class="col-md-8"><select name="order_id" class="form-select" required>
    <option value="">-- pilih pesanan yang sudah terkirim --</option>
    @foreach($orders as $o)
      <option value="{{ $o->id }}" @selected(request('order_id')==$o->id)>
        {{ $o->number }} - {{ $o->customer->name }} (kirim {{ \Carbon\Carbon::parse($o->delivered_date)->format('d/m/Y') }},
        Rp {{ number_format($o->total,0,',','.') }})</option>
    @endforeach
  </select></div>
  <div class="col-md-4"><button class="btn btn-outline-primary w-100">Tampilkan Barang</button></div>
</form>

@if($order)
@php $sisa = $order->total - $order->paid_amount - $order->receivable_cut; @endphp
<div class="alert alert-info py-2">
  Pesanan <b>{{ $order->number }}</b> ({{ $order->customer->name }}). Total Rp {{ number_format($order->total,0,',','.') }},
  sudah dibayar Rp {{ number_format($order->paid_amount,0,',','.') }}, sisa piutang <b>Rp {{ number_format($sisa,0,',','.') }}</b>.
  Saldo: Kas Rp {{ number_format($saldoKas,0,',','.') }} | Bank Rp {{ number_format($saldoBank,0,',','.') }}.
</div>

<form method="POST" action="{{ route('sales-returns.store') }}" class="card card-body">
  @csrf
  <input type="hidden" name="order_id" value="{{ $order->id }}">

  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Tanggal Retur</label>
      <input type="date" name="date" class="form-control" min="{{ $order->delivered_date }}" max="{{ date('Y-m-d') }}"
             value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-9"><label class="form-label">Catatan (opsional)</label>
      <input name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Contoh: ukuran tidak sesuai"></div>
  </div>

  <table class="table table-bordered" id="tbl">
    <thead><tr><th>Barang</th><th class="num">Terkirim</th><th class="num">Sudah Diretur</th>
      <th class="num">Sisa</th><th class="num">Harga</th><th width="160">Jumlah Diretur</th></tr></thead>
    <tbody>
    @foreach($lines as $id => $l)
      <tr>
        <td>{{ $l['item']->name }}
          <input type="hidden" name="items[{{ $id }}][item_id]" value="{{ $id }}"></td>
        <td class="num">{{ number_format($l['sold'],2,',','.') }}</td>
        <td class="num">{{ number_format($l['returned'],2,',','.') }}</td>
        <td class="num">{{ number_format($l['remaining'],2,',','.') }}</td>
        <td class="num">{{ number_format($l['price'],0,',','.') }}</td>
        <td><input type="number" step="0.01" min="0" max="{{ $l['remaining'] }}" data-price="{{ $l['price'] }}"
                   name="items[{{ $id }}][qty]" class="form-control qty"
                   value="{{ old('items.'.$id.'.qty', 0) }}" {{ $l['remaining'] <= 0 ? 'disabled' : '' }}></td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <div class="alert alert-secondary py-2" id="preview">Isi jumlah untuk melihat perhitungan.</div>

  <div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label">Akun pengembalian uang (hanya dipakai bila ada uang yang dikembalikan)</label>
      <select name="refund_account" class="form-select">
        <option value="">-- tidak ada pengembalian --</option>
        <option value="1101" @selected(old('refund_account')=='1101')>Kas</option>
        <option value="1102" @selected(old('refund_account')=='1102')>Bank</option>
      </select></div>
  </div>

  <div><button class="btn btn-warning" onclick="return confirm('Simpan retur? Retur tidak dapat dibatalkan.')">Simpan Retur</button>
       <a href="{{ route('sales-returns.index') }}" class="btn btn-secondary">Batal</a></div>
</form>

@push('scripts')
<script>
const OUTSTANDING = {{ max($sisa, 0) }};
const fmt = v => Math.round(v).toLocaleString('id-ID');

function calc() {
  let total = 0;
  document.querySelectorAll('.qty').forEach(el => {
    total += (parseFloat(el.value) || 0) * parseFloat(el.dataset.price);
  });
  const cut = Math.min(total, OUTSTANDING);
  const refund = total - cut;
  document.getElementById('preview').innerHTML =
    'Nilai retur: <b>Rp ' + fmt(total) + '</b> &middot; memotong piutang: <b>Rp ' + fmt(cut) +
    '</b> &middot; uang dikembalikan: <b>Rp ' + fmt(refund) + '</b>' +
    (refund > 0 ? ' (pilih akun pengembalian di bawah)' : '');
}
document.querySelectorAll('.qty').forEach(el => el.addEventListener('input', calc));
calc();
</script>
@endpush
@endif
@endsection