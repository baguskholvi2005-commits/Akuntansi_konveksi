@extends('layouts.sia')
@section('title','Resep Produk')
@section('content')
<h4>Resep Produk</h4>
<div class="text-muted mb-3">Kebutuhan bahan per 1 unit produk. Dipakai untuk mengisi bahan otomatis saat membuat produksi.</div>
<table class="table bg-white align-middle">
  <thead><tr><th>Kode</th><th>Produk</th><th>Jumlah bahan</th><th></th></tr></thead>
  <tbody>
  @foreach($products as $p)
    <tr><td>{{ $p->code }}</td><td>{{ $p->name }}</td>
      <td>{{ $counts[$p->id] ?? 0 }} bahan</td>
      <td class="text-end"><a href="{{ route('recipes.edit',$p) }}" class="btn btn-sm btn-outline-primary">Atur Resep</a></td></tr>
  @endforeach
  </tbody>
</table>
@endsection