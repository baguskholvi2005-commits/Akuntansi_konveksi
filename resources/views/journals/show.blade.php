@extends('layouts.sia')
@section('title','Detail Jurnal')
@section('content')
@php
  $canPost = in_array(auth()->user()->role, ['admin','keuangan']);
  $canReverse = $canPost && $journal->source_type === null && !$journal->is_reversal && !$journal->reversedBy;
@endphp
<div class="d-flex justify-content-between mb-3">
  <h4>{{ $journal->number }}
    <span class="badge bg-secondary">{{ $journal->source_label }}</span>
    @if($journal->is_reversal)<span class="badge bg-warning text-dark">pembalik</span>@endif
  </h4>
  <a href="{{ route('journals.index') }}" class="btn btn-secondary no-print">Kembali</a>
</div>

<div class="card card-body mb-3">
  <div class="row">
    <div class="col-md-3"><b>Tanggal:</b> {{ \Carbon\Carbon::parse($journal->date)->format('d/m/Y') }}</div>
    <div class="col-md-5"><b>Keterangan:</b> {{ $journal->description }}</div>
    <div class="col-md-4"><b>Dibuat oleh:</b> {{ $journal->creator->name ?? '-' }}</div>
  </div>
  @if($journal->reversalOf)
    <div class="mt-2 text-warning-emphasis">Jurnal ini membalik
      <a href="{{ route('journals.show',$journal->reversalOf) }}">{{ $journal->reversalOf->number }}</a>.</div>
  @endif
  @if($journal->reversedBy)
    <div class="mt-2 text-danger">Jurnal ini sudah dibalik oleh
      <a href="{{ route('journals.show',$journal->reversedBy) }}">{{ $journal->reversedBy->number }}</a>.</div>
  @endif
</div>

<table class="table table-bordered bg-white">
  <thead><tr><th>Akun</th><th>Memo</th><th class="num" width="160">Debit</th><th class="num" width="160">Kredit</th></tr></thead>
  <tbody>
  @foreach($journal->lines as $l)
    <tr>
      <td style="{{ $l->credit > 0 ? 'padding-left:2rem' : '' }}">{{ $l->account->code }} {{ $l->account->name }}</td>
      <td>{{ $l->memo }}</td>
      <td class="num">{{ $l->debit>0 ? number_format($l->debit,0,',','.') : '' }}</td>
      <td class="num">{{ $l->credit>0 ? number_format($l->credit,0,',','.') : '' }}</td>
    </tr>
  @endforeach
  </tbody>
  <tfoot><tr class="table-light"><th colspan="2" class="text-end">Total</th>
    <th class="num">{{ number_format($journal->lines->sum('debit'),0,',','.') }}</th>
    <th class="num">{{ number_format($journal->lines->sum('credit'),0,',','.') }}</th></tr></tfoot>
</table>

@if($canReverse)
<div class="card card-body border-danger no-print" style="max-width:600px">
  <h6>Balik Jurnal Manual</h6>
  <p class="small text-muted mb-2">Jurnal tidak dihapus. Sistem membuat jurnal pembalik (debit dan kredit ditukar) bertanggal hari ini.</p>
  <form method="POST" action="{{ route('journals.reverse',$journal) }}" class="row g-2">@csrf
    <div class="col-md-8"><input name="reason" class="form-control" placeholder="Alasan pembalikan" required></div>
    <div class="col-md-4"><button class="btn btn-outline-danger w-100"
        onclick="return confirm('Balik jurnal ini?')">Balik Jurnal</button></div>
  </form>
</div>
@endif
@endsection