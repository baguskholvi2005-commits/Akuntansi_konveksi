@extends('layouts.sia')
@section('title','Ganti Kata Sandi')
@section('content')
<h4 class="mb-3">Ganti Kata Sandi</h4>
<form method="POST" action="{{ route('account.password.update') }}" class="card card-body" style="max-width:480px">
  @csrf @method('PUT')
  <div class="mb-3"><label class="form-label">Kata sandi saat ini</label>
    <input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
  <div class="mb-3"><label class="form-label">Kata sandi baru (minimal 8 karakter)</label>
    <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
  <div class="mb-3"><label class="form-label">Ulangi kata sandi baru</label>
    <input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password"></div>
  <div><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection