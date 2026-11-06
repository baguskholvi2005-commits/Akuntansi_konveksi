@extends('layouts.sia')
@section('title','Biaya Produksi')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $canOpen = in_array(auth()->user()->role, ['admin','keuangan','gudang']);
  $pc = fn($v) => $t['total'] > 0 ? $v / $t['total'] * 100 : 0;
@endphp
<h4>Laporan Keuangan</h4>
@include('reports._nav')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h5 class="mb-0">Biaya Produksi</h5>
    <div class="text-muted">Bahan baku, tenaga kerja, dan overhead dari produksi yang selesai. Periode {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} s.d. {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div></div>
  <button onclick="window.print()" class="btn btn-outline-secondary no-print"><i class="bi bi-printer"></i> Cetak</button>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Total biaya produksi</div>
    <div class="kpi-value">Rp {{ $f($t['total']) }}</div><div class="small text-muted">{{ $t['count'] }} produksi selesai</div></div></div>
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Tenaga kerja</div>
    <div class="kpi-value">Rp {{ $f($t['labor']) }}</div><div class="small text-muted">{{ number_format($pc($t['labor']),0) }}% dari total</div></div></div>
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Bahan baku terpakai</div>
    <div class="kpi-value">Rp {{ $f($t['material']) }}</div><div class="small text-muted">{{ number_format($pc($t['material']),0) }}% dari total</div></div></div>
  <div class="col-sm-6 col-xl"><div class="card kpi"><div class="kpi-label">Overhead</div>
    <div class="kpi-value">Rp {{ $f($t['overhead']) }}</div><div class="small text-muted">{{ number_format($pc($t['overhead']),0) }}% dari total</div></div></div>
  <div class="col-sm-12 col-xl"><div class="card kpi text-white" style="background:var(--brand)"><div class="kpi-label text-white-50">Rata-rata biaya per unit</div>
    <div class="kpi-value">Rp {{ $f($t['avg']) }}</div><div class="small opacity-75">{{ $f($t['qty']) }} unit diproduksi</div></div></div>
</div>

<form class="card card-body mb-3 no-print">
  <div class="row g-2">
    <div class="col-lg-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor atau produk..."></div>
    <div class="col-lg-2"><select name="cat" class="form-select"><option value="">Semua kategori</option>
      <option value="labor" @selected(request('cat')=='labor')>Tenaga kerja</option><option value="overhead" @selected(request('cat')=='overhead')>Overhead</option></select></div>
    <div class="col-lg-2"><select name="pay" class="form-select"><option value="">Semua status</option>
      <option value="paid" @selected(request('pay')=='paid')>Lunas</option><option value="debt" @selected(request('pay')=='debt')>Masih utang</option></select></div>
    <div class="col-lg-2"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
    <div class="col-lg-2"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
    <div class="col-lg-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-body pb-0"><h6 class="mb-0">Rincian Biaya Produksi</h6>
        <div class="small text-muted mb-2">Biaya dicatat saat produksi diselesaikan (tanggal selesai)</div></div>
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Referensi</th><th>Tgl selesai</th><th>Kategori</th><th>Produk</th><th class="num">Tarif / unit</th><th class="num">Total biaya</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($rows as $e)
          <tr>
            <td class="nowrap fw-semibold">@if($canOpen)<a href="{{ route('productions.show',$e->p) }}" class="text-decoration-none">{{ $e->p->number }}</a>@else{{ $e->p->number }}@endif</td>
            <td class="nowrap">{{ \Carbon\Carbon::parse($e->p->finished_date)->format('d/m/Y') }}</td>
            <td><span class="badge {{ $e->kind=='labor' ? 'bg-primary-subtle text-primary-emphasis' : 'bg-info-subtle text-info-emphasis' }}">{{ $e->label }}</span></td>
            <td>{{ $e->p->item->name }}<div class="small text-muted">{{ number_format($e->p->qty,0,',','.') }} {{ $e->p->item->unit->name }}</div></td>
            <td class="num">Rp {{ $f($e->rate) }}</td>
            <td class="num fw-semibold">Rp {{ $f($e->amount) }}</td>
            <td>@if($e->left > 0)<span class="badge bg-danger-subtle text-danger-emphasis">Utang</span><div class="small text-muted nowrap">sisa {{ $f($e->left) }}</div>
                @else<span class="badge bg-success-subtle text-success-emphasis">Lunas</span>@endif</td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Tidak ada biaya produksi pada filter ini.</div></div></td></tr>
        @endforelse
        </tbody>
      </table></div>
      <div class="d-flex justify-content-between align-items-center p-3">
        <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} baris</div>
        {{ $rows->links('pagination::bootstrap-5') }}
      </div>
    </div>

    <div class="card card-body">
      <h6>Biaya per Produk</h6>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Produk</th><th class="num">Batch</th><th class="num">Unit</th><th class="num">Total biaya</th><th class="num">Biaya / unit</th></tr></thead>
        <tbody>
        @forelse($byProduct as $b)
          <tr><td>{{ $b['name'] }}</td><td class="num">{{ $b['batches'] }}</td><td class="num">{{ number_format($b['qty'],0,',','.') }}</td>
            <td class="num">Rp {{ $f($b['total']) }}</td><td class="num fw-semibold">Rp {{ $f($b['unit']) }}</td></tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr>
        @endforelse
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card card-body mb-3">
      <h6>Struktur Alokasi Biaya</h6>
      @foreach(['Bahan baku' => $t['material'], 'Tenaga kerja' => $t['labor'], 'Overhead' => $t['overhead']] as $lbl => $v)
        <div class="d-flex justify-content-between small"><span>{{ $lbl }}</span><span><b>Rp {{ $f($v) }}</b> ({{ number_format($pc($v),1,',','.') }}%)</span></div>
        <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width:{{ $pc($v) }}%;background-color:var(--accent)"></div></div>
      @endforeach
    </div>

    <div class="card card-body mb-3">
      <h6>Utang Produksi (posisi saat ini)</h6>
      <div class="d-flex justify-content-between"><span>Gaji (2103)</span><b>Rp {{ $f($debt['labor']) }}</b></div>
      <div class="d-flex justify-content-between"><span>Overhead (2101)</span><b>Rp {{ $f($debt['overhead']) }}</b></div>
      <div class="small text-muted mt-1">Dibayar pada periode ini: Rp {{ $f($paidInPeriod) }}</div>
      <a class="small" href="{{ route('reports.debts') }}">Lihat rincian utang</a>
    </div>

    <div class="card card-body">
      <div class="d-flex justify-content-between mb-2"><h6 class="mb-0">Jurnal Biaya Produksi</h6>
        <span class="badge {{ $journalOk ? 'bg-success' : 'bg-danger' }}">{{ $journalOk ? 'COCOK' : 'SELISIH' }}</span></div>
      <table class="table table-sm mb-0">
        <thead><tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
        <tbody>
        @forelse($journal as $j)
          <tr><td>{{ $j->code }} {{ $j->name }}</td><td class="num">{{ $j->d > 0 ? $f($j->d) : '' }}</td><td class="num">{{ $j->c > 0 ? $f($j->c) : '' }}</td></tr>
        @empty
          <tr><td colspan="3" class="text-muted text-center">Tidak ada jurnal pada periode ini.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr class="table-light"><th>Total</th><th class="num">{{ $f($journal->sum('d')) }}</th><th class="num">{{ $f($journal->sum('c')) }}</th></tr></tfoot>
      </table>
      <div class="small text-muted mt-2">Debit 1302 harus sama dengan tenaga kerja + overhead di atas.</div>
    </div>
  </div>
</div>
@endsection