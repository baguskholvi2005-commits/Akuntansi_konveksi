@extends('layouts.sia')
@section('title','Hasil Hitung Fisik')
@section('content')
<h4>Hasil Hitung Fisik (Stok Opname)</h4>
<div class="alert alert-info py-2" style="max-width:700px">Catat jumlah barang yang benar-benar ada di gudang.
  Stok dan jurnal <b>belum berubah</b> sampai admin atau keuangan menyetujui.</div>
<form method="POST" action="{{ route('stock-adjustments.store') }}" class="card card-body" style="max-width:700px">
  @csrf
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Tanggal Hitung</label>
      <input type="date" name="date" class="form-control" max="{{ date('Y-m-d') }}" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-8"><label class="form-label">Barang</label>
      <select name="item_id" class="form-select" required>
        <option value="">-- pilih barang --</option>
        @foreach($items as $i)
          <option value="{{ $i->id }}" @selected(old('item_id')==$i->id)>
            {{ $i->code }} - {{ $i->name }} (stok sistem {{ rtrim(rtrim(number_format($i->stock_qty,2,'.',''),'0'),'.') }} {{ $i->unit->name }})</option>
        @endforeach
      </select></div>
    <div class="col-md-5"><label class="form-label">Jumlah hasil hitung fisik</label>
      <input type="number" step="0.01" min="0" name="counted_qty" class="form-control" value="{{ old('counted_qty') }}" required></div>
    <div class="col-md-7"><label class="form-label">Alasan</label>
      <input name="reason" class="form-control" value="{{ old('reason') }}" placeholder="Contoh: Stok opname akhir bulan" required></div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Simpan Draft</button>
    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection