@extends('layouts.sia')
@section('title', $row ? 'Ubah Barang' : 'Tambah Barang')
@section('content')
<h4>{{ $row ? 'Ubah' : 'Tambah' }} Barang</h4>
<form method="POST" action="{{ $row ? route('items.update',$row) : route('items.store') }}"
      class="card card-body" style="max-width:600px">
  @csrf @if($row) @method('PUT') @endif
  <div class="mb-3"><label class="form-label">Kode</label>
    <input name="code" class="form-control" value="{{ old('code', $row->code ?? '') }}" required></div>
  <div class="mb-3"><label class="form-label">Nama</label>
    <input name="name" class="form-control" value="{{ old('name', $row->name ?? '') }}" required></div>
  <div class="mb-3"><label class="form-label">Jenis</label>
    <select name="type" class="form-select" required>
      <option value="raw_material" @selected(old('type', $row->type ?? '')=='raw_material')>Bahan baku</option>
      <option value="product" @selected(old('type', $row->type ?? '')=='product')>Produk jadi</option>
    </select></div>
  <div class="mb-3"><label class="form-label">Kategori</label>
    <select name="category_id" class="form-select">
      <option value="">-- tanpa kategori --</option>
      @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected(old('category_id', $row->category_id ?? '')==$c->id)>{{ $c->name }}</option>
      @endforeach
    </select></div>
  <div class="mb-3"><label class="form-label">Satuan</label>
    <select name="unit_id" class="form-select" required>
      @foreach($units as $u)
        <option value="{{ $u->id }}" @selected(old('unit_id', $row->unit_id ?? '')==$u->id)>{{ $u->name }}</option>
      @endforeach
    </select></div>
  <div class="mb-3"><label class="form-label">Harga Jual (untuk produk jadi)</label>
    <input type="number" step="0.01" min="0" name="sale_price" class="form-control"
           value="{{ old('sale_price', $row->sale_price ?? 0) }}" required></div>
  <div class="mb-3"><label class="form-label">Batas Minimum Stok</label>
    <input type="number" step="0.01" min="0" name="min_stock" class="form-control"
           value="{{ old('min_stock', $row->min_stock ?? 0) }}" required></div>
  <div><button class="btn btn-primary">Simpan</button>
       <a href="{{ route('items.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection