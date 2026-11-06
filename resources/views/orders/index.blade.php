@extends('layouts.sia')
@section('title','Pesanan dan Penjualan')
@section('content')
@php
  $f = fn($v) => number_format($v, 0, ',', '.');
  $role = auth()->user()->role;
  $badge = ['menunggu_pembayaran'=>'bg-secondary-subtle text-secondary-emphasis','dalam_produksi'=>'bg-warning-subtle text-warning-emphasis',
            'selesai'=>'bg-info-subtle text-info-emphasis','terkirim'=>'bg-success-subtle text-success-emphasis'];
  $payOf = function ($o) {
      $left = $o->total - $o->paid_amount - $o->receivable_cut;
      if ($left <= 0.005) return ['Lunas', 'bg-success-subtle text-success-emphasis', 0];
      if ($o->paid_amount > 0) return ['Sebagian', 'bg-warning-subtle text-warning-emphasis', $left];
      return ['Belum dibayar', 'bg-danger-subtle text-danger-emphasis', $left];
  };
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h4 class="mb-0">Pesanan dan Penjualan</h4>
    <div class="text-muted">Pesanan pelanggan, uang muka, pengiriman, dan pelunasan</div></div>
  @if(in_array($role, ['admin','keuangan']))<a href="{{ route('orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Pesanan Baru</a>@endif
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Nilai pesanan bulan ini</div>
    <div class="kpi-value">Rp {{ $f($stats['month_total']) }}</div><div class="small text-muted">{{ $stats['month_count'] }} pesanan</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Kas masuk bulan ini</div>
    <div class="kpi-value text-success">Rp {{ $f($stats['cash_in']) }}</div><div class="small text-muted">Uang muka dan pelunasan</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Piutang belum tertagih</div>
    <div class="kpi-value text-danger">Rp {{ $f($stats['recv']) }}</div><div class="small text-muted">{{ $stats['recv_count'] }} pesanan terkirim belum lunas</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Antrean aktif</div>
    <div class="kpi-value">{{ $stats['active'] }} pesanan</div><div class="small text-muted">Belum terkirim</div></div></div>
</div>

<form class="card card-body mb-3">
  <div class="row g-2">
    <div class="col-lg-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor atau pelanggan..."></div>
    <div class="col-lg-2"><select name="pay" class="form-select"><option value="">Status bayar: semua</option>
      <option value="unpaid" @selected(request('pay')=='unpaid')>Belum dibayar</option>
      <option value="partial" @selected(request('pay')=='partial')>Sebagian</option>
      <option value="paid" @selected(request('pay')=='paid')>Lunas</option></select></div>
    <div class="col-lg-2"><select name="status" class="form-select"><option value="">Status pesanan: semua</option>
      @foreach(['menunggu_pembayaran','dalam_produksi','selesai','terkirim'] as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ str_replace('_',' ',$s) }}</option>@endforeach</select></div>
    <div class="col-lg-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-lg-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-lg-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-body pb-0"><h6 class="mb-2">Daftar Pesanan <span class="badge bg-info-subtle text-info-emphasis">{{ $rows->total() }}</span></h6></div>
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>No. dan tanggal</th><th>Pelanggan</th><th>Barang</th><th class="num">Nilai</th><th>Pembayaran</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($rows as $o)
          @php [$pl, $pc, $left] = $payOf($o); $first = $o->items->first(); $more = $o->items->count() - 1; @endphp
          <tr class="{{ $pick && $pick->id == $o->id ? 'table-active' : '' }}">
            <td class="nowrap"><a href="{{ route('orders.index', array_merge(request()->query(), ['pick'=>$o->id])) }}" class="fw-semibold text-decoration-none">{{ $o->number }}</a>
              <div class="small text-muted">{{ \Carbon\Carbon::parse($o->date)->format('d/m/Y') }}</div></td>
            <td>{{ $o->customer->name }}</td>
            <td class="small">{{ $first ? $first->item->name : '-' }}{{ $more > 0 ? ' +'.$more.' lainnya' : '' }}</td>
            <td class="num fw-semibold">Rp {{ $f($o->total) }}@if($left > 0)<div class="small text-danger">sisa {{ $f($left) }}</div>@endif</td>
            <td><span class="badge {{ $pc }}">{{ $pl }}</span></td>
            <td><span class="badge {{ $badge[$o->status] }}">{{ str_replace('_',' ',$o->status) }}</span>
              <a href="{{ route('orders.show',$o) }}" class="btn btn-sm btn-light border ms-1" title="Detail"><i class="bi bi-eye"></i></a></td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i><div class="mt-2">Belum ada pesanan untuk filter ini.</div></div></td></tr>
        @endforelse
        </tbody>
      </table></div>
      <div class="d-flex justify-content-between align-items-center p-3">
        <div class="small text-muted">Menampilkan {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} pesanan</div>
        {{ $rows->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card card-body">
      <h6 class="mb-3">Pratinjau Pesanan dan Jurnal</h6>
      @if($pick)
        @php [$pl, $pc, $left] = $payOf($pick); @endphp
        <div class="d-flex justify-content-between"><b>{{ $pick->number }}</b><span class="badge {{ $badge[$pick->status] }}">{{ str_replace('_',' ',$pick->status) }}</span></div>
        <div class="text-muted small mb-2">{{ $pick->customer->name }}</div>
        <table class="table table-sm mb-2"><tbody>
          @foreach($pick->items as $it)
            <tr><td>{{ $it->item->name }}<div class="small text-muted">{{ number_format($it->qty,0,',','.') }} {{ $it->item->unit->name }} x Rp {{ $f($it->unit_price) }}</div></td>
              <td class="num">{{ $f($it->subtotal) }}</td></tr>
          @endforeach
          <tr class="fw-bold"><td>Total</td><td class="num">{{ $f($pick->total) }}</td></tr>
          <tr><td>Dibayar</td><td class="num">{{ $f($pick->paid_amount) }}</td></tr>
          @if($pick->receivable_cut > 0)<tr><td>Dipotong retur</td><td class="num">{{ $f($pick->receivable_cut) }}</td></tr>@endif
          <tr><td>Sisa</td><td class="num {{ $left > 0 ? 'text-danger' : '' }}">{{ $f($left) }}</td></tr>
        </tbody></table>
        <div class="small fw-semibold mb-1">Jurnal terkait</div>
        @forelse($pickJournals as $j)
          <div class="small text-muted">{{ $j->number }}</div>
          <table class="table table-sm mb-2"><tbody>
            @foreach($j->lines as $l)
              <tr><td style="{{ $l->credit>0 ? 'padding-left:1.5rem' : '' }}">{{ $l->account->code }} {{ $l->account->name }}</td>
                <td class="num">{{ $l->debit>0 ? $f($l->debit) : $f($l->credit) }}</td></tr>
            @endforeach
          </tbody></table>
        @empty
          <div class="small text-muted mb-2">Belum ada jurnal. Jurnal pendapatan dan HPP terbentuk saat barang dikirim.</div>
        @endforelse
        <a href="{{ route('orders.show',$pick) }}" class="btn btn-primary btn-sm">Buka Detail</a>
      @else
        <div class="empty"><i class="bi bi-receipt"></i><div class="mt-2">Pilih pesanan untuk melihat pratinjau.</div></div>
      @endif
    </div>
  </div>
</div>
@endsection