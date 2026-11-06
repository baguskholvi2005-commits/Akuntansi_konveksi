@extends('layouts.sia')
@section('title','Kartu Persediaan')
@section('content')
@php
  $fq = fn($v) => number_format($v, 2, ',', '.');
  $fv = fn($v) => number_format($v, 0, ',', '.');
  $role = auth()->user()->role;
  $ops  = in_array($role, ['admin','keuangan','gudang']);
  $src = [
    \App\Models\Purchase::class        => 'purchases.show',
    \App\Models\Production::class      => 'productions.show',
    \App\Models\SalesOrder::class      => 'orders.show',
    \App\Models\SalesReturn::class     => 'sales-returns.show',
    \App\Models\StockAdjustment::class => 'stock-adjustments.show',
  ];
  if ($item) {
    $q = (float) $item->stock_qty; $mn = (float) $item->min_stock;
    $status = $q <= 0 ? 'habis' : (($mn > 0 && $q <= $mn) ? 'menipis' : 'aman');
    $badge  = ['aman' => 'bg-success-subtle text-success-emphasis', 'menipis' => 'bg-warning-subtle text-warning-emphasis', 'habis' => 'bg-danger-subtle text-danger-emphasis'][$status];
  }
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><h4 class="mb-0">Kartu Persediaan</h4>
    <div class="text-muted">Mutasi masuk, keluar, dan saldo persediaan per barang (metode rata-rata tertimbang)</div></div>
  <div class="d-flex gap-2 no-print">
    @if($data)@include('partials.export')<button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Cetak</button>@endif
    @if($ops)<a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Penyesuaian Stok</a>@endif
  </div>
</div>

<form class="card card-body mb-3 no-print">
  <div class="row g-2">
    <div class="col-lg-6"><label class="form-label small mb-0">Barang</label>
      <select name="item_id" class="form-select" required>
        <option value="">-- pilih barang --</option>
        @foreach($items->groupBy(fn($i) => ($i->type=='raw_material' ? 'Bahan baku' : 'Produk jadi').' - '.($i->category->name ?? 'Tanpa kategori')) as $grp => $list)
          <optgroup label="{{ $grp }}">
            @foreach($list as $i)<option value="{{ $i->id }}" @selected(request('item_id')==$i->id)>{{ $i->code }} - {{ $i->name }}</option>@endforeach
          </optgroup>
        @endforeach
      </select></div>
    <div class="col-lg-2"><label class="form-label small mb-0">Dari tanggal</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-lg-2"><label class="form-label small mb-0">Sampai tanggal</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tampilkan</button></div>
  </div>
</form>

@if($data)
<div class="card card-body mb-3">
  <div class="d-flex flex-wrap align-items-center gap-2">
    <h5 class="mb-0 me-2">{{ $item->code }} - {{ $item->name }}</h5>
    <span class="badge bg-secondary-subtle text-secondary-emphasis">Satuan: {{ $item->unit->name }}</span>
    <span class="badge bg-info-subtle text-info-emphasis">Harga rata-rata: Rp {{ $fq($item->avg_cost) }}</span>
    <span class="badge bg-secondary-subtle text-secondary-emphasis">Stok sistem: {{ $fq($item->stock_qty) }}</span>
    <span class="badge bg-secondary-subtle text-secondary-emphasis">Batas minimum: {{ $fq($item->min_stock) }}</span>
    <span class="badge {{ $badge }}">{{ ucfirst($status) }}</span>
  </div>
  <div class="small text-muted mt-1">Periode {{ request('from') ? \Carbon\Carbon::parse(request('from'))->format('d/m/Y') : 'awal' }} s.d. {{ request('to') ? \Carbon\Carbon::parse(request('to'))->format('d/m/Y') : 'sekarang' }}</div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Saldo awal</div>
    <div class="kpi-value">{{ $fq($data['opening']['qty']) }} <small class="fs-6">{{ $item->unit->name }}</small></div>
    <div class="small text-muted">Nilai Rp {{ $fv($data['opening']['value']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total masuk</div>
    <div class="kpi-value text-success">{{ $fq($data['total']['in_qty']) }} <small class="fs-6">{{ $item->unit->name }}</small></div>
    <div class="small text-muted">Nilai Rp {{ $fv($data['total']['in_value']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total keluar</div>
    <div class="kpi-value text-danger">{{ $fq($data['total']['out_qty']) }} <small class="fs-6">{{ $item->unit->name }}</small></div>
    <div class="small text-muted">Nilai Rp {{ $fv($data['total']['out_value']) }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi text-white" style="background:var(--brand)"><div class="kpi-label text-white-50">Saldo akhir</div>
    <div class="kpi-value">{{ $fq($data['closing']['qty']) }} <small class="fs-6">{{ $item->unit->name }}</small></div>
    <div class="small opacity-75">Nilai Rp {{ $fv($data['closing']['value']) }}</div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-9">
    <div class="card mb-3">
      <div class="card-body pb-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Mutasi Buku Besar Persediaan</h6>
        @if($recon['ok'])<span class="badge bg-success">KARTU = STOK SISTEM</span>
        @else<span class="badge bg-danger">SELISIH: kartu {{ $fq($recon['card']) }} vs stok {{ $fq($recon['stock']) }}</span>@endif
      </div>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr><th rowspan="2">Tanggal</th><th rowspan="2">Referensi</th><th rowspan="2">Keterangan</th>
            <th colspan="3" class="text-center">Masuk</th><th colspan="3" class="text-center">Keluar</th><th colspan="3" class="text-center">Saldo</th></tr>
          <tr><th class="num">Qty</th><th class="num">Harga</th><th class="num">Nilai</th>
            <th class="num">Qty</th><th class="num">Harga</th><th class="num">Nilai</th>
            <th class="num">Qty</th><th class="num">Harga</th><th class="num">Nilai</th></tr>
        </thead>
        <tbody>
          <tr class="table-light"><td colspan="9"><b>Saldo awal</b></td>
            <td class="num"><b>{{ $fq($data['opening']['qty']) }}</b></td><td class="num"><b>{{ $fq($data['opening']['cost']) }}</b></td><td class="num"><b>{{ $fv($data['opening']['value']) }}</b></td></tr>
        @forelse($data['rows'] as $m)
          @php
            $route = $src[$m->source_type] ?? null;
            $can = $route && ($m->source_type === \App\Models\SalesReturn::class ? in_array($role, ['admin','keuangan']) : $ops);
            $in = $m->in_qty > 0; $out = $m->out_qty > 0;
          @endphp
          <tr class="{{ $in ? '' : '' }}">
            <td class="nowrap">{{ \Carbon\Carbon::parse($m->date)->format('d/m/Y') }}</td>
            <td class="nowrap">@if($can)<a href="{{ route($route, $m->source_id) }}" class="badge text-bg-light border text-decoration-none">{{ $m->reference }}</a>@else<span class="badge text-bg-light border">{{ $m->reference }}</span>@endif</td>
            <td>{{ $m->description }}</td>
            <td class="num text-success">{{ $in ? $fq($m->in_qty) : '' }}</td><td class="num">{{ $in ? $fq($m->in_cost) : '' }}</td><td class="num">{{ $in ? $fv($m->in_value) : '' }}</td>
            <td class="num text-danger">{{ $out ? $fq($m->out_qty) : '' }}</td><td class="num">{{ $out ? $fq($m->out_cost) : '' }}</td><td class="num">{{ $out ? $fv($m->out_value) : '' }}</td>
            <td class="num fw-semibold">{{ $fq($m->balance_qty) }}</td><td class="num">{{ $fq($m->balance_cost) }}</td><td class="num fw-semibold">{{ $fv($m->balance_value) }}</td>
          </tr>
        @empty
          <tr><td colspan="12"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Tidak ada mutasi pada periode ini.</div></div></td></tr>
        @endforelse
        </tbody>
        <tfoot>
          <tr class="table-light"><th colspan="3" class="text-end">Total mutasi</th>
            <th class="num">{{ $fq($data['total']['in_qty']) }}</th><th></th><th class="num">{{ $fv($data['total']['in_value']) }}</th>
            <th class="num">{{ $fq($data['total']['out_qty']) }}</th><th></th><th class="num">{{ $fv($data['total']['out_value']) }}</th><th colspan="3"></th></tr>
          <tr class="table-light"><th colspan="9" class="text-end">Saldo akhir</th>
            <th class="num">{{ $fq($data['closing']['qty']) }}</th><th class="num">{{ $fq($data['closing']['cost']) }}</th><th class="num">{{ $fv($data['closing']['value']) }}</th></tr>
        </tfoot>
      </table></div>
    </div>

    @if(count($chart['labels']) > 0)
    <div class="card card-body">
      <h6 class="mb-0">Tren Saldo {{ $item->unit->name }}</h6>
      <div class="small text-muted mb-2">Saldo kuantitas setelah tiap transaksi{{ $chart['min'] > 0 ? ', dengan garis batas minimum' : '' }}</div>
      <div style="height:260px"><canvas id="trend"></canvas></div>
    </div>
    @endif
  </div>

  <div class="col-xl-3">
    <div class="card card-body {{ $status != 'aman' && $item->type == 'raw_material' ? 'border-warning' : '' }}">
      <h6>Status Stok</h6>
      <div class="d-flex justify-content-between"><span>Stok saat ini</span><b>{{ $fq($item->stock_qty) }} {{ $item->unit->name }}</b></div>
      <div class="d-flex justify-content-between"><span>Batas minimum</span><b>{{ $fq($item->min_stock) }}</b></div>
      <div class="d-flex justify-content-between mb-2"><span>Nilai persediaan</span><b>Rp {{ $fv($item->stock_qty * $item->avg_cost) }}</b></div>
      <span class="badge {{ $badge }} align-self-start">{{ ucfirst($status) }}</span>
      @if($item->type == 'raw_material' && $status != 'aman' && in_array($role, ['admin','keuangan','gudang']))
        <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm mt-3"><i class="bi bi-cart-plus"></i> Buat Pembelian</a>
      @endif
    </div>
  </div>
</div>
@else
  <div class="alert alert-secondary">Pilih barang lalu klik <b>Tampilkan</b>. Kosongkan tanggal untuk melihat seluruh mutasi.</div>
@endif
@endsection

@if($data && count($chart['labels']) > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const C = @json($chart), css = getComputedStyle(document.documentElement);
const ds = [{ label: 'Saldo', data: C.qty, borderColor: css.getPropertyValue('--brand').trim(), backgroundColor: 'transparent', tension: .25, pointRadius: 4 }];
if (C.min > 0) ds.push({ label: 'Batas minimum', data: C.labels.map(() => C.min), borderColor: '#F59E0B', borderDash: [6,6], pointRadius: 0 });
new Chart(document.getElementById('trend'), { type: 'line', data: { labels: C.labels, datasets: ds },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
             scales: { y: { beginAtZero: true }, x: { ticks: { maxRotation: 40 } } } } });
</script>
@endpush
@endif