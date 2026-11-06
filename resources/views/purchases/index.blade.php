@extends('layouts.sia')
@section('title','Pembelian')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $canExport = in_array(auth()->user()->role, ['admin','keuangan']);
  $q = request()->query();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0">Daftar Transaksi Pembelian</h4>
    <div class="text-muted">Kelola faktur pembelian kain, benang, tinta, dan aksesoris</div>
  </div>
  <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Pembelian Baru</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Total belanja bulan ini</div>
    <div class="kpi-value">Rp {{ $f($stats['month_total']) }}</div><div class="small text-muted">Pembelian yang sudah diposting</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Faktur belum lunas</div>
    <div class="kpi-value text-danger">Rp {{ $f($stats['unpaid_total']) }}</div><div class="small text-muted">{{ $stats['unpaid_count'] }} faktur kredit</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Draft belum diposting</div>
    <div class="kpi-value">{{ $stats['drafts'] }}</div><div class="small text-muted">Belum mengubah stok dan jurnal</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Hasil filter</div>
    <div class="kpi-value">{{ $stats['filtered'] }}</div><div class="small text-muted">transaksi</div></div></div>
</div>

<form class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-lg-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor atau pemasok..."></div>
    <div class="col-lg-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control" title="Dari tanggal"></div>
    <div class="col-lg-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control" title="Sampai tanggal"></div>
    <div class="col-lg-2"><select name="status" class="form-select">
      <option value="">Semua status</option>
      <option value="draft" @selected(request('status')=='draft')>Draft</option>
      <option value="confirmed" @selected(request('status')=='confirmed')>Diposting</option>
      <option value="unpaid" @selected(request('status')=='unpaid')>Belum lunas</option>
    </select></div>
    <div class="col-lg-2"><select name="supplier_id" class="form-select">
      <option value="">Semua pemasok</option>
      @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id')==$s->id)>{{ $s->name }}</option>@endforeach
    </select></div>
    <div class="col-lg-1 d-flex gap-1">
      <button class="btn btn-primary flex-grow-1">Terapkan</button>
      @if($canExport)
      <div class="dropdown"><button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" title="Ekspor"><i class="bi bi-upload"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="{{ route('export', ['report'=>'purchases','format'=>'pdf'] + array_intersect_key($q, array_flip(['from','to']))) }}">Laporan PDF</a></li>
          <li><a class="dropdown-item" href="{{ route('export', ['report'=>'purchases','format'=>'xlsx'] + array_intersect_key($q, array_flip(['from','to']))) }}">Laporan Excel</a></li>
        </ul></div>
      @endif
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr>
      <th width="36"><input type="checkbox" class="form-check-input" id="selAll"></th>
      <th>Nomor Faktur</th><th>Tanggal</th><th>Pemasok</th><th>Metode</th><th class="num">Total</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    @forelse($rows as $p)
      @php
        $first = $p->items->first(); $more = $p->items->count() - 1;
        if ($p->status === 'draft')                                       [$pl, $pc] = ['Draft', 'bg-secondary-subtle text-secondary-emphasis'];
        elseif ($p->payment_method === 'tunai' || $p->paid_amount >= $p->total) [$pl, $pc] = ['Lunas', 'bg-success-subtle text-success-emphasis'];
        elseif ($p->paid_amount > 0)                                      [$pl, $pc] = ['Sebagian', 'bg-warning-subtle text-warning-emphasis'];
        else                                                              [$pl, $pc] = ['Belum dibayar', 'bg-danger-subtle text-danger-emphasis'];
      @endphp
      <tr>
        <td><input type="checkbox" class="form-check-input sel" data-total="{{ $p->total }}"></td>
        <td><div class="fw-semibold nowrap">{{ $p->number }}</div>
          <div class="small text-muted text-truncate" style="max-width:260px">{{ $first ? $first->item->name : '-' }}{{ $more > 0 ? ' +'.$more.' lainnya' : '' }}</div></td>
        <td class="nowrap">{{ \Carbon\Carbon::parse($p->date)->format('d/m/Y') }}</td>
        <td><div class="d-flex align-items-center gap-2"><span class="avatar">{{ mb_strtoupper(mb_substr($p->supplier->name,0,1)) }}</span>
          <span>{{ $p->supplier->name }}</span></div></td>
        <td><span class="badge bg-primary-subtle text-primary-emphasis">{{ $p->payment_method == 'tunai' ? 'Tunai' : 'Kredit' }}</span></td>
        <td class="num fw-semibold">Rp {{ $f($p->total) }}</td>
        <td><span class="badge {{ $pc }}">{{ $pl }}</span>
          @if($p->due_date && $p->status==='confirmed' && $p->payment_method==='kredit' && $p->paid_amount < $p->total)
            <div class="small {{ \Carbon\Carbon::parse($p->due_date)->endOfDay()->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">Tempo {{ \Carbon\Carbon::parse($p->due_date)->format('d/m/Y') }}</div>
          @endif</td>
        <td class="text-end"><a href="{{ route('purchases.show',$p) }}" class="btn btn-sm btn-light border" title="Detail"><i class="bi bi-eye"></i></a></td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty"><i class="bi bi-inbox"></i>
        <div class="mt-2 fw-semibold">Belum ada transaksi</div>
        <div class="small mb-3">Ubah filter, atau buat pembelian pertama.</div>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">+ Pembelian Baru</a></div></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>

  <div id="selBar" class="d-none px-3 py-2 d-flex justify-content-between align-items-center" style="background:#EEF1F8">
    <span><span class="badge bg-info-subtle text-info-emphasis">RINGKASAN SELEKSI</span> <span id="selCount">0</span> transaksi terpilih</span>
    <span>Subtotal: <b id="selSum">Rp 0</b></span>
  </div>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">
    <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} transaksi</div>
    <form class="d-flex align-items-center gap-2">
      @foreach(request()->except('per_page','page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
      <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach([10,25,50] as $n)<option value="{{ $n }}" @selected(request('per_page',10)==$n)>{{ $n }} baris</option>@endforeach
      </select>
    </form>
    {{ $rows->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection

@push('scripts')
<script>
const sels = [...document.querySelectorAll('.sel')], bar = document.getElementById('selBar');
function sum() {
  const on = sels.filter(c => c.checked);
  document.getElementById('selCount').textContent = on.length;
  document.getElementById('selSum').textContent = 'Rp ' + Math.round(on.reduce((a,c) => a + parseFloat(c.dataset.total), 0)).toLocaleString('id-ID');
  bar.classList.toggle('d-none', on.length === 0);
}
sels.forEach(c => c.addEventListener('change', sum));
document.getElementById('selAll').addEventListener('change', e => { sels.forEach(c => c.checked = e.target.checked); sum(); });
</script>
@endpush