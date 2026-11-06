@extends('layouts.sia')
@section('title', $row ? 'Ubah Pemasok' : 'Tambah Pemasok')
@section('content')
<h4>{{ $row ? 'Ubah' : 'Tambah' }} Pemasok</h4>
<form method="POST" action="{{ $row ? route('suppliers.update',$row) : route('suppliers.store') }}"
      class="card card-body" style="max-width:600px">
  @csrf @if($row) @method('PUT') @endif
  <div class="mb-3"><label class="form-label">Kode</label>
    <input name="code" class="form-control" value="{{ old('code', $row->code ?? '') }}" required></div>
  <div class="mb-3"><label class="form-label">Nama</label>
    <input name="name" class="form-control" value="{{ old('name', $row->name ?? '') }}" required></div>
  <div class="mb-3"><label class="form-label">Telepon</label>
    <input name="phone" class="form-control" value="{{ old('phone', $row->phone ?? '') }}"></div>
  <div class="mb-3"><label class="form-label">Alamat</label>
    <textarea name="address" class="form-control">{{ old('address', $row->address ?? '') }}</textarea></div>
  <div><button class="btn btn-primary">Simpan</button>
       <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection