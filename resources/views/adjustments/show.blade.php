@extends('layouts.sia')
@section('title','Detail Penyesuaian')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $canApprove = in_array(auth()->user()->role, ['admin','keuangan']);
  $now  = (float) $a->item->stock_qty;
  $diff = round((float) $a->counted_qty - $now, 2);
@endphp
<div class="d-flex justify-content-between mb-3">
  <h4>{{ $a->number }} <span class="badge bg-{{ $a->status=='posted' ? 'success' : 'secondary' }}">{{ $a->status=='posted' ? 'disetujui' : 'menunggu persetujuan' }}</span></h4>
  <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary">Kembali</a>
</div>

<div class="card card-body mb-3">
  <div class="row">
    <div class="col-md-3"><b>Tanggal:</b> {{ \Carbon\Carbon::parse($a->date)->format('d/m/Y') }}</div>
    <div class="col-md-5"><b>Barang:</b> {{ $a->item->code }} - {{ $a->item->name }}</div>
    <div class="col-md-4"><b>Dibuat oleh:</b> {{ $a->creator->name ?? '-' }}</div>
  </div>
  <div class="mt-2"><b>Alasan:</b> {{ $a->reason }}</div>
  @if($a->status=='posted')
    <div class="mt-2"><b>Disetujui oleh:</b> {{ $a->approver->name ?? '-' }} pada {{ $a->approved_at?->format('d/m/Y H:i') }}</div>
  @endif
</div>

<table class="table table-bordered bg-white" style="max-width:600px">
  @if($a->status=='posted')
    <tr><th>Stok sistem (saat disetujui)</th><td class="num">{{ number_format($a->system_qty,2,',','.') }} {{ $a->item->unit->name }}</td></tr>
    <tr><th>Hasil hitung fisik</th><td class="num">{{ number_format($a->counted_qty,2,',','.') }}</td></tr>
    <tr><th>Selisih</th><td class="num {{ $a->diff_qty < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($a->diff_qty,2,',','.') }}</td></tr>
    <tr><th>Harga per unit</th><td class="num">{{ number_format($a->unit_cost,2,',','.') }}</td></tr>
    <tr><th>Nilai selisih</th><td class="num">{{ $f($a->value) }}</td></tr>
  @else
    <tr><th>Stok sistem saat ini</th><td class="num">{{ number_format($now,2,',','.') }} {{ $a->item->unit->name }}</td></tr>
    <tr><th>Hasil hitung fisik</th><td class="num">{{ number_format($a->counted_qty,2,',','.') }}</td></tr>
    <tr><th>Perkiraan selisih</th><td class="num {{ $diff < 0 ? 'text-danger' : '' }}">{{ number_format($diff,2,',','.') }}</td></tr>
  @endif
</table>

@if($a->status=='draft')
  <p class="small text-muted">Selisih dihitung ulang terhadap stok saat persetujuan, karena stok bisa berubah sejak draft dibuat.</p>
  <div class="d-flex gap-2 mb-4">
    @if($canApprove)
    <form method="POST" action="{{ route('stock-adjustments.approve',$a) }}" class="row g-2 align-items-end"
          onsubmit="return confirm('Setujui? Stok dan jurnal akan berubah dan tidak bisa dibatalkan.')">@csrf
      <div class="col-auto"><label class="form-label small mb-0">Harga satuan (hanya jika barang belum punya harga rata-rata)</label>
        <input type="number" step="0.01" min="0.01" name="unit_cost" class="form-control"></div>
      <div class="col-auto"><button class="btn btn-success">Setujui dan Posting</button></div>
    </form>
    @else
      <div class="alert alert-warning mb-0">Menunggu persetujuan admin atau keuangan.</div>
    @endif
    <form method="POST" action="{{ route('stock-adjustments.destroy',$a) }}" onsubmit="return confirm('Hapus draft ini?')">
      @csrf @method('DELETE')<button class="btn btn-outline-danger">Hapus Draft</button></form>
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
          <td class="num">{{ $l->debit>0 ? $f($l->debit) : '' }}</td>
          <td class="num">{{ $l->credit>0 ? $f($l->credit) : '' }}</td></tr>
    @endforeach
    </tbody>
  </table>
@endforeach
@endif
@endsection