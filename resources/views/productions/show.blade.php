@extends('layouts.sia')
@section('title','Detail Produksi')
@section('content')
@php
  $role = auth()->user()->role;
  $canFinish = in_array($role, ['admin','keuangan']);
  $badge = ['draft'=>'secondary','proses'=>'warning','selesai'=>'success'][$production->status];
@endphp
<div class="d-flex justify-content-between mb-3">
  <h4>{{ $production->number }} <span class="badge bg-{{ $badge }}">{{ $production->status }}</span></h4>
  <div class="d-flex gap-2">
    @if($production->status != 'draft')<a href="{{ route('productions.ticket',$production) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Cetak Tiket Potong</a>@endif
    <a href="{{ route('productions.index') }}" class="btn btn-secondary">Kembali</a>
  </div>
</div>

<div class="card card-body mb-3">
  <div class="row">
    <div class="col-md-3"><b>Tanggal mulai:</b> {{ \Carbon\Carbon::parse($production->date)->format('d/m/Y') }}</div>
    <div class="col-md-5"><b>Produk:</b> {{ $production->item->name }}</div>
    <div class="col-md-4"><b>Jumlah:</b> {{ number_format($production->qty,2,',','.') }} {{ $production->item->unit->name }}</div>
  </div>
  @if($production->notes)<div class="mt-2"><b>Catatan:</b> {{ $production->notes }}</div>@endif
</div>

@if($production->status != 'draft')
@php $stages = \App\Models\Production::STAGES; $keys = array_keys($stages); $cur = array_search($production->stage, $keys); @endphp
<div class="card card-body mb-3">
  <h6>Tahapan Produksi <span class="small text-muted fw-normal">(penanda saja, tidak mengubah jurnal)</span></h6>
  <div class="d-flex flex-wrap gap-2">
  @foreach($stages as $k => $label)
    @php $i = array_search($k, $keys); $done = $production->status=='selesai' || $i < $cur; $now = $production->status=='proses' && $i == $cur; @endphp
    <form method="POST" action="{{ route('productions.stage',$production) }}">@csrf
      <input type="hidden" name="stage" value="{{ $k }}">
      <button class="btn btn-sm {{ $done ? 'btn-success' : ($now ? 'btn-primary' : 'btn-outline-secondary') }}" {{ $production->status!='proses' ? 'disabled' : '' }}>
        @if($done)<i class="bi bi-check2"></i> @endif{{ $label }}</button>
    </form>
  @endforeach
  </div>
</div>
@endif

<h6>Bahan Baku</h6>
<table class="table table-bordered bg-white">
  <thead><tr><th>Bahan</th><th class="num">Jumlah</th>
    @if($production->status=='draft')<th class="num">Stok Tersedia</th>@else<th class="num">Biaya/Unit</th><th class="num">Total</th>@endif
  </tr></thead>
  <tbody>
  @foreach($production->materials as $m)
    <tr>
      <td>{{ $m->item->name }}</td>
      <td class="num">{{ number_format($m->qty,2,',','.') }} {{ $m->item->unit->name }}</td>
      @if($production->status=='draft')
        <td class="num {{ $m->item->stock_qty < $m->qty ? 'text-danger fw-bold' : '' }}">
          {{ number_format($m->item->stock_qty,2,',','.') }}</td>
      @else
        <td class="num">{{ number_format($m->unit_cost,2,',','.') }}</td>
        <td class="num">{{ number_format($m->total,0,',','.') }}</td>
      @endif
    </tr>
  @endforeach
  </tbody>
</table>

@if($production->status != 'draft')
<table class="table table-bordered bg-white" style="max-width:520px">
  <tr><th>Biaya Bahan Baku</th><td class="num">{{ number_format($production->material_cost,0,',','.') }}</td></tr>
  @if($production->status=='selesai')
  <tr><th>Tenaga Kerja Langsung</th><td class="num">{{ number_format($production->labor_cost,0,',','.') }}</td></tr>
  <tr><th>Overhead</th><td class="num">{{ number_format($production->overhead_cost,0,',','.') }}</td></tr>
  <tr class="table-light"><th>Total Biaya Produksi</th><td class="num fw-bold">{{ number_format($production->total_cost,0,',','.') }}</td></tr>
  <tr class="table-light"><th>Harga Pokok per Unit</th><td class="num fw-bold">{{ number_format($production->unit_cost,2,',','.') }}</td></tr>
  @endif
</table>
@endif

@if($production->status=='draft')
  <div class="d-flex gap-2 mb-4">
    <form method="POST" action="{{ route('productions.start',$production) }}"
          onsubmit="return confirm('Mulai produksi? Bahan baku akan langsung dipakai dan tidak bisa dibatalkan.')">@csrf
      <button class="btn btn-success">Mulai Produksi</button></form>
    <form method="POST" action="{{ route('productions.destroy',$production) }}"
          onsubmit="return confirm('Hapus draft ini?')">@csrf @method('DELETE')
      <button class="btn btn-outline-danger">Hapus Draft</button></form>
  </div>
@endif

@if($production->status=='proses' && $canFinish)
<div class="card card-body mb-4">
  <h6>Selesaikan Produksi</h6>
  <form method="POST" action="{{ route('productions.finish',$production) }}" class="row g-3">@csrf
    <div class="col-md-3"><label class="form-label">Tanggal Selesai</label>
      <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-3"><label class="form-label">Biaya Tenaga Kerja</label>
      <input type="number" step="0.01" min="0" name="labor_cost" class="form-control" value="{{ old('labor_cost',0) }}" required></div>
    <div class="col-md-3"><label class="form-label">Dibayar Dengan</label>
      <select name="labor_source" class="form-select">
        <option value="kas">Kas</option><option value="bank">Bank</option><option value="utang">Utang Gaji</option></select></div>
    <div class="col-md-3"></div>
    <div class="col-md-3"></div>
    <div class="col-md-3"><label class="form-label">Biaya Overhead</label>
      <input type="number" step="0.01" min="0" name="overhead_cost" class="form-control" value="{{ old('overhead_cost',0) }}" required></div>
    <div class="col-md-3"><label class="form-label">Dibayar Dengan</label>
      <select name="overhead_source" class="form-select">
        <option value="kas">Kas</option><option value="bank">Bank</option><option value="utang">Utang Usaha</option></select></div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-success w-100" onclick="return confirm('Selesaikan produksi? Tidak bisa diubah setelah ini.')">Selesaikan Produksi</button></div>
  </form>
</div>
@elseif($production->status=='proses')
  <div class="alert alert-warning">Produksi sedang berjalan. Hanya admin atau keuangan yang dapat menyelesaikannya.</div>
@endif

@if($production->status=='selesai' && $production->total_cost > 0)
@php
  $parts = ['Bahan baku' => $production->material_cost, 'Tenaga kerja' => $production->labor_cost, 'Overhead' => $production->overhead_cost];
  $gp = $production->item->sale_price - $production->unit_cost;
@endphp
<div class="card card-body mb-3" style="max-width:640px">
  <h6>Struktur Harga Pokok</h6>
  @foreach($parts as $lbl => $v)
    @php $pct = $v / $production->total_cost * 100; @endphp
    <div class="d-flex justify-content-between small"><span>{{ $lbl }}</span><span><b>Rp {{ number_format($v,0,',','.') }}</b> ({{ number_format($pct,0) }}%)</span></div>
    <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width:{{ $pct }}%;background-color:var(--accent)"></div></div>
  @endforeach
  <hr class="my-2">
  <div class="d-flex justify-content-between"><span>HPP per unit</span><b>Rp {{ number_format($production->unit_cost,0,',','.') }}</b></div>
  <div class="d-flex justify-content-between"><span>Harga jual (master barang)</span><b>Rp {{ number_format($production->item->sale_price,0,',','.') }}</b></div>
  <div class="d-flex justify-content-between"><span>Laba kotor per unit (indikatif)</span><b class="{{ $gp >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($gp,0,',','.') }}</b></div>
</div>
@endif

@if($production->status=='selesai' && ($production->labor_payable > 0 || $production->overhead_payable > 0))
<h6>Utang Produksi</h6>
<table class="table table-bordered table-sm bg-white" style="max-width:600px">
  <thead><tr><th>Jenis</th><th class="num">Dicatat Sebagai Utang</th><th class="num">Sisa Utang</th></tr></thead>
  <tbody>
    @if($production->labor_payable > 0)
    <tr><td>Tenaga kerja (Utang Gaji 2103)</td>
        <td class="num">{{ number_format($production->labor_payable,0,',','.') }}</td>
        <td class="num">{{ number_format($left['labor'],0,',','.') }}</td></tr>
    @endif
    @if($production->overhead_payable > 0)
    <tr><td>Overhead (Utang Usaha 2101)</td>
        <td class="num">{{ number_format($production->overhead_payable,0,',','.') }}</td>
        <td class="num">{{ number_format($left['overhead'],0,',','.') }}</td></tr>
    @endif
  </tbody>
</table>

@if($canFinish)
  @foreach(['labor' => 'Bayar Utang Gaji', 'overhead' => 'Bayar Utang Overhead'] as $kind => $label)
    @if($left[$kind] > 0)
    <div class="card card-body mb-3">
      <h6>{{ $label }}</h6>
      <form method="POST" action="{{ route('productions.pay',$production) }}" class="row g-2">@csrf
        <input type="hidden" name="kind" value="{{ $kind }}">
        <div class="col-md-3"><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
        <div class="col-md-3"><input type="number" step="0.01" name="amount" class="form-control" value="{{ $left[$kind] }}" required></div>
        <div class="col-md-3"><select name="account_code" class="form-select">
            <option value="1101">Kas</option><option value="1102">Bank</option></select></div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Catat Pembayaran</button></div>
      </form>
    </div>
    @endif
  @endforeach
@endif
@endif

@if($production->payments->count())
<h6>Riwayat Pembayaran Utang</h6>
<table class="table table-sm table-bordered bg-white" style="max-width:600px">
  <thead><tr><th>Tanggal</th><th>Jenis</th><th>Akun</th><th class="num">Jumlah</th></tr></thead>
  <tbody>
  @foreach($production->payments as $p)
    <tr><td>{{ \Carbon\Carbon::parse($p->date)->format('d/m/Y') }}</td>
        <td>{{ $p->kind=='labor' ? 'Tenaga kerja' : 'Overhead' }}</td>
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