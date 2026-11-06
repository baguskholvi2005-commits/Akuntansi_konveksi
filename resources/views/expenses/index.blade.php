@extends('layouts.sia')
@section('title','Pengeluaran')
@section('content')
<div class="d-flex justify-content-between mb-3">
  <h4>Pengeluaran Operasional</h4>
  <a href="{{ route('expenses.create') }}" class="btn btn-primary">+ Catat Pengeluaran</a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor / keterangan..."></div>
  <div class="col-md-3"><select name="category" class="form-select">
    <option value="">Semua kategori</option>
    @foreach(\App\Models\Expense::CATEGORIES as $key => $c)
      <option value="{{ $key }}" @selected(request('category')==$key)>{{ $c[0] }}</option>
    @endforeach
  </select></div>
  <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
  <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="alert alert-secondary py-2">Total pengeluaran (sesuai filter, tidak termasuk yang dibatalkan):
  <b>Rp {{ number_format($total,0,',','.') }}</b></div>

<table class="table table-bordered bg-white">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Dibayar dari</th>
    <th class="num">Jumlah</th><th>Bukti</th><th>Status</th><th width="110"></th></tr></thead>
  <tbody>
  @forelse($rows as $r)
    <tr class="{{ $r->status=='void' ? 'text-decoration-line-through text-muted' : '' }}">
      <td>{{ $r->number }}</td>
      <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
      <td>{{ \App\Models\Expense::CATEGORIES[$r->category][0] ?? $r->category }}</td>
      <td>{{ $r->description }}</td>
      <td>{{ $r->payment_account_code=='1101' ? 'Kas' : 'Bank' }}</td>
      <td class="num">{{ number_format($r->amount,0,',','.') }}</td>
      <td>@if($r->proof_path)<a href="{{ asset('storage/'.$r->proof_path) }}" target="_blank">Lihat</a>@else - @endif</td>
      <td>
        <span class="badge bg-{{ $r->status=='posted' ? 'success' : 'secondary' }}">{{ $r->status=='posted' ? 'tercatat' : 'dibatalkan' }}</span>
        @if($r->status=='void')<div class="small">{{ $r->void_reason }}</div>@endif
      </td>
      <td>
        @if($r->status=='posted')
        <form method="POST" action="{{ route('expenses.void',$r) }}"
              onsubmit="var a=prompt('Alasan pembatalan:'); if(!a){return false;} this.reason.value=a; return true;">
          @csrf <input type="hidden" name="reason">
          <button class="btn btn-sm btn-outline-danger">Batalkan</button>
        </form>
        @endif
      </td>
    </tr>
  @empty
    <tr><td colspan="9" class="text-center text-muted">Belum ada pengeluaran.</td></tr>
  @endforelse
  </tbody>
</table>
{{ $rows->links('pagination::bootstrap-5') }}
@endsection