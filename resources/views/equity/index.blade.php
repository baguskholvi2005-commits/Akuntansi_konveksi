@extends('layouts.sia')
@section('title','Modal dan Prive')
@section('content')
<div class="d-flex justify-content-between mb-3">
  <h4>Modal dan Prive</h4>
  <a href="{{ route('equity.create') }}" class="btn btn-primary">+ Catat Baru</a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-4"><select name="type" class="form-select">
    <option value="">Semua jenis</option>
    <option value="modal" @selected(request('type')=='modal')>Modal</option>
    <option value="prive" @selected(request('type')=='prive')>Prive</option>
  </select></div>
  <div class="col-md-3"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
  <div class="col-md-3"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<table class="table table-bordered bg-white">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Akun Kas/Bank</th><th>Catatan</th>
    <th class="num">Jumlah</th><th>Status</th><th width="110"></th></tr></thead>
  <tbody>
  @forelse($rows as $r)
    <tr class="{{ $r->status=='void' ? 'text-decoration-line-through text-muted' : '' }}">
      <td>{{ $r->number }}</td>
      <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
      <td>{{ $r->type=='modal' ? 'Setoran modal' : 'Prive' }}</td>
      <td>{{ $r->account_code=='1101' ? 'Kas' : 'Bank' }}</td>
      <td>{{ $r->notes }}</td>
      <td class="num">{{ number_format($r->amount,0,',','.') }}</td>
      <td>
        <span class="badge bg-{{ $r->status=='posted' ? 'success' : 'secondary' }}">{{ $r->status=='posted' ? 'tercatat' : 'dibatalkan' }}</span>
        @if($r->status=='void')<div class="small">{{ $r->void_reason }}</div>@endif
      </td>
      <td>
        @if($r->status=='posted')
        <form method="POST" action="{{ route('equity.void',$r) }}"
              onsubmit="var a=prompt('Alasan pembatalan:'); if(!a){return false;} this.reason.value=a; return true;">
          @csrf <input type="hidden" name="reason">
          <button class="btn btn-sm btn-outline-danger">Batalkan</button>
        </form>
        @endif
      </td>
    </tr>
  @empty
    <tr><td colspan="8" class="text-center text-muted">Belum ada transaksi.</td></tr>
  @endforelse
  </tbody>
</table>
{{ $rows->links('pagination::bootstrap-5') }}
@endsection