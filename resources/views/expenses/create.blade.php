@extends('layouts.sia')
@section('title','Catat Pengeluaran')
@section('content')
<h4>Catat Pengeluaran</h4>
<div class="alert alert-info py-2" style="max-width:700px">
  Saldo saat ini: Kas <b>Rp {{ number_format($saldoKas,0,',','.') }}</b> |
  Bank <b>Rp {{ number_format($saldoBank,0,',','.') }}</b>
</div>
<form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data"
      class="card card-body" style="max-width:700px">
  @csrf
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Tanggal</label>
      <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-8"><label class="form-label">Kategori</label>
      <select name="category" class="form-select" required>
        @foreach(\App\Models\Expense::CATEGORIES as $key => $c)
          <option value="{{ $key }}" @selected(old('category')==$key)>{{ $c[0] }} (akun {{ $c[1] }})</option>
        @endforeach
      </select></div>
    <div class="col-12"><label class="form-label">Keterangan</label>
      <input name="description" class="form-control" value="{{ old('description') }}" placeholder="Contoh: Listrik bulan November" required></div>
    <div class="col-md-6"><label class="form-label">Nominal (Rp)</label>
      <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required></div>
    <div class="col-md-6"><label class="form-label">Dibayar dari</label>
      <select name="payment_account" class="form-select" required>
        <option value="1101" @selected(old('payment_account')=='1101')>Kas</option>
        <option value="1102" @selected(old('payment_account')=='1102')>Bank</option>
      </select></div>
    <div class="col-12"><label class="form-label">Bukti transaksi (opsional: JPG, PNG, PDF, maks 2 MB)</label>
      <input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Simpan</button>
    <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection