@extends('layouts.sia')
@section('title','Catat Modal / Prive')
@section('content')
<h4>Catat Modal / Prive</h4>
<div class="alert alert-info py-2" style="max-width:700px">
  Saldo saat ini: Kas <b>Rp {{ number_format($saldoKas,0,',','.') }}</b> |
  Bank <b>Rp {{ number_format($saldoBank,0,',','.') }}</b>
</div>
<form method="POST" action="{{ route('equity.store') }}" class="card card-body" style="max-width:700px">
  @csrf
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Tanggal</label>
      <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-8"><label class="form-label">Jenis</label>
      <select name="type" class="form-select" required>
        <option value="modal" @selected(old('type')=='modal')>Setoran modal (pemilik memasukkan uang)</option>
        <option value="prive" @selected(old('type')=='prive')>Prive (pemilik mengambil uang)</option>
      </select></div>
    <div class="col-md-6"><label class="form-label">Nominal (Rp)</label>
      <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required></div>
    <div class="col-md-6"><label class="form-label">Masuk / keluar lewat</label>
      <select name="account_code" class="form-select" required>
        <option value="1101" @selected(old('account_code')=='1101')>Kas</option>
        <option value="1102" @selected(old('account_code')=='1102')>Bank</option>
      </select></div>
    <div class="col-12"><label class="form-label">Catatan (opsional)</label>
      <input name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Contoh: Modal awal usaha"></div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Simpan</button>
    <a href="{{ route('equity.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection