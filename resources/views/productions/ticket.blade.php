@extends('layouts.sia')
@section('title','Tiket Potong')
@section('content')
@php $S = \App\Models\Setting::values(); @endphp
<div class="no-print mb-3"><button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Cetak</button>
  <a href="{{ route('productions.show',$production) }}" class="btn btn-light border">Kembali</a></div>

<div class="card card-body" style="max-width:800px;margin:auto">
  <div class="text-center border-bottom pb-2 mb-3">
    <div class="fs-5 fw-bold">{{ $S['company_name'] }}</div>
    <div class="fw-semibold">TIKET POTONG DAN PRODUKSI</div>
  </div>
  <div class="row mb-3">
    <div class="col-6"><b>No. Produksi:</b> {{ $production->number }}<br><b>Tanggal mulai:</b> {{ \Carbon\Carbon::parse($production->date)->format('d/m/Y') }}</div>
    <div class="col-6"><b>Produk:</b> {{ $production->item->name }}<br><b>Jumlah:</b> {{ number_format($production->qty,0,',','.') }} {{ $production->item->unit->name }}</div>
  </div>
  @if($production->notes)<div class="mb-3"><b>Catatan:</b> {{ $production->notes }}</div>@endif

  <table class="table table-bordered">
    <thead><tr><th>Bahan Baku</th><th class="num">Jumlah Dikeluarkan</th><th>Diterima (paraf)</th></tr></thead>
    <tbody>
    @foreach($production->materials as $m)
      <tr><td>{{ $m->item->name }}</td><td class="num">{{ number_format($m->qty,2,',','.') }} {{ $m->item->unit->name }}</td><td></td></tr>
    @endforeach
    </tbody>
  </table>

  <table class="table table-bordered">
    <thead><tr><th>Tahap</th><th width="120">Tanggal</th><th width="120">Jumlah jadi</th><th width="120">Paraf</th></tr></thead>
    <tbody>
    @foreach(\App\Models\Production::STAGES as $label)
      <tr style="height:42px"><td>{{ $label }}</td><td></td><td></td><td></td></tr>
    @endforeach
    </tbody>
  </table>
  <div class="small text-muted text-center">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }}</div>
</div>
@endsection