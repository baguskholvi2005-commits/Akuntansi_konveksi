@extends('layouts.sia')
@section('title','Pengguna')
@section('content')
@php $roles = ['admin'=>'Admin','keuangan'=>'Keuangan','gudang'=>'Gudang','pemilik'=>'Pemilik']; @endphp
<h4 class="mb-3">Pengaturan</h4>
@include('settings._tabs')

<div class="d-flex justify-content-between mb-3">
  <div class="text-muted">Admin mengatur ulang kata sandi pengguna lewat tombol <b>Ubah</b>.</div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUser">+ Tambah Pengguna</button>
</div>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th width="190"></th></tr></thead>
  <tbody>
  @foreach($users as $u)
    <tr class="{{ $u->is_active ? '' : 'text-muted' }}">
      <td class="fw-semibold">{{ $u->name }} @if($u->id === auth()->id())<span class="badge bg-info-subtle text-info-emphasis">Anda</span>@endif</td>
      <td>{{ $u->email }}</td>
      <td><span class="badge bg-primary-subtle text-primary-emphasis">{{ $roles[$u->role] ?? $u->role }}</span></td>
      <td><span class="badge {{ $u->is_active ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
        {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
      <td class="nowrap">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit{{ $u->id }}">Ubah</button>
        @if($u->id !== auth()->id())
        <form method="POST" action="{{ route('settings.users.toggle',$u) }}" class="d-inline"
              onsubmit="return confirm('{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} pengguna ini?')">@csrf
          <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
        </form>
        @endif
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>

{{-- Modal tambah --}}
<div class="modal fade" id="addUser" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('settings.users.store') }}" class="modal-content">@csrf
  <div class="modal-header"><h6 class="modal-title">Tambah Pengguna</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Peran</label>
      <select name="role" class="form-select">@foreach($roles as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div class="mb-1"><label class="form-label">Kata sandi (minimal 8 karakter)</label><input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
</form></div></div>

{{-- Modal ubah, satu per pengguna --}}
@foreach($users as $u)
<div class="modal fade" id="edit{{ $u->id }}" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('settings.users.update',$u) }}" class="modal-content">@csrf @method('PUT')
  <div class="modal-header"><h6 class="modal-title">Ubah {{ $u->name }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Nama</label><input name="name" class="form-control" value="{{ $u->name }}" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="{{ $u->email }}" disabled></div>
    <div class="mb-3"><label class="form-label">Peran</label>
      <select name="role" class="form-select" {{ $u->id === auth()->id() ? 'disabled' : '' }}>
        @foreach($roles as $k=>$v)<option value="{{ $k }}" @selected($u->role==$k)>{{ $v }}</option>@endforeach</select>
      @if($u->id === auth()->id())<div class="small text-muted">Peran akun sendiri tidak dapat diubah.</div>@endif
      {{-- select disabled tidak terkirim, jadi kirim nilai aslinya --}}
      @if($u->id === auth()->id())<input type="hidden" name="role" value="{{ $u->role }}">@endif</div>
    <div class="mb-1"><label class="form-label">Kata sandi baru (kosongkan bila tidak diubah)</label>
      <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
</form></div></div>
@endforeach
@endsection