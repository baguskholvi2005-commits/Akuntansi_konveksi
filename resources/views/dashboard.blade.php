@extends('layouts.sia')
@section('title','Dashboard')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $today = now();
  $qStart = $today->copy()->firstOfQuarter();
  $tabs = [
    'Hari ini'    => [$today->format('Y-m-d'), $today->format('Y-m-d')],
    'Bulan ini'   => [$today->format('Y-m-01'), $today->format('Y-m-d')],
    'Kuartal ini' => [$qStart->format('Y-m-d'), $today->format('Y-m-d')],
    'Tahun ini'   => [$today->format('Y-01-01'), $today->format('Y-m-d')],
  ];
  $role   = auth()->user()->role;
  $canBuy = in_array($role, ['admin','keuangan','gudang']);

  // [kelas chip, ikon, teks]. $mode: 'up' = naik itu baik, 'down' = naik itu buruk, null = netral
  $chip = function ($pct, $mode) {
      if ($pct === null) return ['chip-flat', 'bi-dash', 'Belum ada pembanding'];
      if (abs($pct) < 0.05) return ['chip-flat', 'bi-dash', 'Tetap'];
      $up   = $pct > 0;
      $good = $mode === null ? null : ($mode === 'up' ? $up : !$up);
      $cls  = $good === null ? 'chip-flat' : ($good ? 'chip-up' : 'chip-down');
      return [$cls, $up ? 'bi-arrow-up-right' : 'bi-arrow-down-right', ($up ? '+' : '') . number_format($pct, 1, ',', '.') . '%'];
  };
  $statusChip = ['menunggu_pembayaran'=>'bg-secondary-subtle text-secondary-emphasis','dalam_produksi'=>'bg-warning-subtle text-warning-emphasis',
                 'selesai'=>'bg-info-subtle text-info-emphasis','terkirim'=>'bg-success-subtle text-success-emphasis'];
  $invTotal = max($ops['inventory_total'], 0.01);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
  <div>
    <h4 class="mb-0">Ringkasan Eksekutif dan Keuangan</h4>
    <div class="text-muted">Selamat datang, {{ auth()->user()->name }}.
      @if($full) Periode: <b>{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</b> s.d. <b>{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</b>@endif</div>
  </div>
  @if($full)
  <div class="d-flex flex-wrap align-items-center gap-2">
    <div class="seg">
      @foreach($tabs as $label => [$a, $b])
        <a href="{{ route('dashboard', ['from'=>$a,'to'=>$b]) }}" class="{{ $from==$a && $to==$b ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
    <button class="btn btn-light border" type="button" data-bs-toggle="collapse" data-bs-target="#custom"><i class="bi bi-calendar3"></i> Atur tanggal</button>
  </div>
  @endif
</div>

@if($full)
<div class="collapse mb-3" id="custom">
  <form class="card card-body row g-2 flex-row align-items-end">
    <div class="col-md-3"><label class="form-label small mb-0">Dari tanggal</label><input type="date" name="from" value="{{ $from }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label small mb-0">Sampai tanggal</label><input type="date" name="to" value="{{ $to }}" class="form-control" required></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
  </form>
</div>

@php
  $cmp = 'vs ' . \Carbon\Carbon::parse($fin['prev_from'])->format('d/m') . ' - ' . \Carbon\Carbon::parse($fin['prev_to'])->format('d/m/Y');
  $margin = $fin['sales'] > 0 ? round($fin['net'] / $fin['sales'] * 100, 1) : null;
@endphp

{{-- ========== KARTU UTAMA ========== --}}
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div class="kpi-label">Penjualan (terkirim)</div><div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div></div>
    <div class="kpi-value mb-2">Rp {{ $f($fin['sales']) }}</div>
    @php [$c,$i,$t] = $chip($fin['change']['sales'],'up'); @endphp
    <span class="chip {{ $c }}"><i class="bi {{ $i }}"></i>{{ $t }}</span> <span class="small text-muted">{{ $cmp }}</span>
  </div></div>

  <div class="col-sm-6 col-xl-3"><div class="card kpi">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div class="kpi-label">Pembelian bahan baku</div><div class="kpi-icon"><i class="bi bi-cart3"></i></div></div>
    <div class="kpi-value mb-2">Rp {{ $f($fin['purchases']) }}</div>
    @php [$c,$i,$t] = $chip($fin['change']['purchases'],null); @endphp
    <span class="chip {{ $c }}"><i class="bi {{ $i }}"></i>{{ $t }}</span> <span class="small text-muted">{{ $cmp }}</span>
  </div></div>

  <div class="col-sm-6 col-xl-3"><div class="card kpi">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div class="kpi-label">Pengeluaran operasional</div><div class="kpi-icon"><i class="bi bi-wallet2"></i></div></div>
    <div class="kpi-value mb-2">Rp {{ $f($fin['expenses']) }}</div>
    @php [$c,$i,$t] = $chip($fin['change']['expenses'],'down'); @endphp
    <span class="chip {{ $c }}"><i class="bi {{ $i }}"></i>{{ $t }}</span> <span class="small text-muted">{{ $cmp }}</span>
  </div></div>

  <div class="col-sm-6 col-xl-3"><div class="card kpi {{ $fin['net'] < 0 ? 'border-danger' : '' }}">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div class="kpi-label">{{ $fin['net'] >= 0 ? 'Laba bersih' : 'Rugi bersih' }}</div>
      <div class="kpi-icon"><i class="bi bi-coin"></i></div></div>
    <div class="kpi-value mb-2 {{ $fin['net'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ $f($fin['net']) }}</div>
    @php [$c,$i,$t] = $chip($fin['change']['net'],'up'); @endphp
    <span class="chip {{ $c }}"><i class="bi {{ $i }}"></i>{{ $t }}</span>
    <span class="small text-muted">{{ $margin !== null ? 'Margin '.number_format($margin,1,',','.').'%' : 'Belum ada penjualan' }}</span>
  </div></div>
</div>

{{-- ========== KARTU POSISI ========== --}}
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="card kpi kpi-sm d-flex flex-row gap-3 align-items-center">
    <div class="kpi-icon bg-white"><i class="bi bi-bank"></i></div>
    <div class="min-w-0"><div class="kpi-label">Kas dan Bank</div><div class="kpi-value" style="font-size:1.15rem">Rp {{ $f($fin['cash']) }}</div>
      <div class="small text-muted nowrap">Kas {{ $f($fin['cash_only']) }} | Bank {{ $f($fin['bank_only']) }}</div></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi kpi-sm d-flex flex-row gap-3 align-items-center">
    <div class="kpi-icon bg-white"><i class="bi bi-receipt"></i></div>
    <div><div class="kpi-label">Utang usaha</div><div class="kpi-value" style="font-size:1.15rem">Rp {{ $f($fin['payables']) }}</div>
      <a class="small" href="{{ route('reports.debts') }}">{{ $fin['payable_count'] }} faktur pembelian belum lunas</a></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi kpi-sm d-flex flex-row gap-3 align-items-center">
    <div class="kpi-icon bg-white"><i class="bi bi-hourglass-split"></i></div>
    <div><div class="kpi-label">Piutang usaha</div><div class="kpi-value" style="font-size:1.15rem">Rp {{ $f($fin['receivables']) }}</div>
      <a class="small" href="{{ route('reports.debts') }}">{{ $fin['receivable_count'] }} pesanan belum lunas</a></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi kpi-sm d-flex flex-row gap-3 align-items-center">
    <div class="kpi-icon bg-white"><i class="bi bi-boxes"></i></div>
    <div><div class="kpi-label">Nilai persediaan</div><div class="kpi-value" style="font-size:1.15rem">Rp {{ $f($ops['inventory_total']) }}</div>
      <a class="small" href="{{ route('inventory.report') }}">Lihat laporan</a></div></div></div>
</div>
@else
{{-- Gudang: hanya angka operasional --}}
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Bahan baku menipis / habis</div>
    <div class="kpi-value">{{ $ops['low_raw']->count() }} barang</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Produksi berjalan</div>
    <div class="kpi-value">{{ $ops['wip_count'] }}</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Pesanan belum terkirim</div>
    <div class="kpi-value">{{ $ops['pending_orders'] }}</div></div></div>
</div>
@endif

{{-- ========== GRAFIK ========== --}}
<div class="row g-3 mb-4">
  @if($full)
  <div class="col-xl-8"><div class="card card-body h-100">
    <h6 class="mb-0">Tren Keuangan</h6>
    <div class="small text-muted mb-3">Penjualan, pembelian, dan pengeluaran operasional, enam bulan berakhir di bulan "Sampai"</div>
    <div style="height:320px"><canvas id="chartBulanan"></canvas></div>
  </div></div>
  @endif

  <div class="{{ $full ? 'col-xl-4' : 'col-xl-6' }}"><div class="card card-body h-100">
    <h6 class="mb-0">Komposisi Persediaan</h6>
    <div class="small text-muted mb-3">Nilai per tahap</div>
    @if($ops['inventory_total'] > 0)
      <div class="position-relative mx-auto" style="height:200px;max-width:200px">
        <canvas id="chartStok"></canvas>
        <div class="position-absolute top-50 start-50 translate-middle text-center">
          <div class="small text-muted">Total</div><div class="fw-bold">{{ number_format($ops['inventory_total']/1000000, 1, ',', '.') }} jt</div></div>
      </div>
      <div class="mt-3">
        @foreach([['Bahan baku',$ops['raw_value'],'#S1'],['Dalam proses',$ops['wip_value'],'#S2'],['Produk jadi',$ops['fg_value'],'#S3']] as $k => [$lbl,$val,$x])
          <div class="d-flex justify-content-between align-items-center py-1">
            <span><span class="dot me-2 sw{{ $k }}"></span>{{ $lbl }}</span>
            <span class="nowrap"><b>Rp {{ $f($val) }}</b> <span class="text-muted small">({{ number_format($val / $invTotal * 100, 0) }}%)</span></span>
          </div>
        @endforeach
      </div>
    @else
      <div class="empty"><i class="bi bi-boxes"></i><div class="mt-2">Belum ada persediaan.</div></div>
    @endif
  </div></div>
</div>

{{-- ========== TABEL ========== --}}
<div class="row g-3">
  <div class="col-xl-7"><div class="card card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div><h6 class="mb-0">Bahan Baku Perlu Restock
        @if($ops['low_raw']->count())<span class="badge bg-danger-subtle text-danger-emphasis ms-1">{{ $ops['low_raw']->count() }}</span>@endif</h6>
        <div class="small text-muted">Stok di bawah batas minimum</div></div>
      @if($canBuy)<a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-cart-plus"></i> Buat Pembelian</a>@endif
    </div>
    <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Kode</th><th>Nama</th><th class="num">Stok</th><th class="num">Batas Min.</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($ops['low_raw'] as $i)
        <tr>
          <td class="nowrap">{{ $i->code }}</td><td>{{ $i->name }}</td>
          <td class="num {{ $i->status=='habis' ? 'text-danger fw-bold' : 'text-warning-emphasis fw-bold' }}">{{ number_format($i->qty,2,',','.') }} {{ $i->unit }}</td>
          <td class="num">{{ number_format($i->min,2,',','.') }}</td>
          <td><span class="badge {{ $i->status=='habis' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}">{{ $i->status=='habis' ? 'Habis' : 'Menipis' }}</span></td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted py-4">Semua stok bahan baku aman.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </div></div>

  <div class="col-xl-5"><div class="card card-body">
    <h6 class="mb-0">Pesanan Terbaru</h6>
    <div class="small text-muted mb-3">{{ $ops['pending_orders'] }} pesanan belum terkirim</div>
    @forelse($ops['recent_orders'] as $o)
      @php $link = in_array($role, ['admin','keuangan','gudang']); @endphp
      <div class="border rounded-3 p-3 mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <b class="nowrap">@if($link)<a href="{{ route('orders.show',$o) }}" class="text-decoration-none">{{ $o->number }}</a>@else{{ $o->number }}@endif</b>
          <span class="badge {{ $statusChip[$o->status] }}">{{ str_replace('_',' ',$o->status) }}</span>
        </div>
        <div class="text-truncate">{{ $o->customer->name }}</div>
        <div class="d-flex justify-content-between small text-muted">
          <span>{{ rtrim(rtrim(number_format($o->qty_sum,2,',','.'),'0'),',') }} pcs</span>
          <span class="fw-semibold text-dark nowrap">Rp {{ $f($o->total) }}</span></div>
      </div>
    @empty
      <div class="empty"><i class="bi bi-bag"></i><div class="mt-2">Belum ada pesanan.</div></div>
    @endforelse
  </div></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const css = getComputedStyle(document.documentElement);
const brand = css.getPropertyValue('--brand').trim(), accent = css.getPropertyValue('--accent').trim(), amber = '#F59E0B';
const rupiah = v => Number(v).toLocaleString('id-ID');

@if($full)
const C = @json($chart);
new Chart(document.getElementById('chartBulanan'), {
  type: 'bar',
  data: { labels: C.labels, datasets: [
    { label: 'Penjualan',               data: C.sales,     backgroundColor: brand,  borderRadius: 6 },
    { label: 'Pembelian',               data: C.purchases, backgroundColor: accent, borderRadius: 6 },
    { label: 'Pengeluaran operasional', data: C.expenses,  backgroundColor: amber,  borderRadius: 6 }
  ]},
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => c.dataset.label + ': Rp ' + rupiah(c.parsed.y) } } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => rupiah(v) } }, x: { grid: { display: false } } } }
});
@endif

@if($ops['inventory_total'] > 0)
const colors = [brand, amber, accent];
document.querySelectorAll('.sw0,.sw1,.sw2').forEach(el => { el.style.background = colors[parseInt(el.className.match(/sw(\d)/)[1])]; });
new Chart(document.getElementById('chartStok'), {
  type: 'doughnut',
  data: { labels: ['Bahan baku','Dalam proses','Produk jadi'],
          datasets: [{ data: [{{ $ops['raw_value'] }}, {{ $ops['wip_value'] }}, {{ $ops['fg_value'] }}], backgroundColor: colors, borderWidth: 0 }] },
  options: { cutout: '72%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': Rp ' + rupiah(c.parsed) } } } }
});
@endif
</script>
@endpush