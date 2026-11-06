@extends('layouts.sia')
@section('title','Profil Perusahaan')
@section('content')
<h4 class="mb-3">Pengaturan</h4>
@include('settings._tabs')

<form method="POST" action="{{ route('settings.company.save') }}" enctype="multipart/form-data" class="row g-4">
  @csrf
  <div class="col-lg-5">
    <div class="card card-body">
      <h6 class="mb-3">Logo</h6>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="logo-box">
          @if($s['logo_path'])<img id="logoPrev" src="{{ asset('storage/'.$s['logo_path']) }}" alt="Logo">
          @else<img id="logoPrev" src="" alt="" style="display:none"><span id="logoEmpty" class="text-muted small">Belum ada</span>@endif
        </div>
        <div class="small text-muted">PNG, JPG, atau WebP. Maksimal 2 MB. Disarankan persegi atau latar transparan.</div>
      </div>
      <div class="dropzone mb-2">
        <i class="bi bi-cloud-arrow-up fs-3"></i>
        <div>Seret gambar ke sini atau klik untuk memilih</div>
        <input type="file" name="logo" id="logoInput" accept=".png,.jpg,.jpeg,.webp">
      </div>
      @if($s['logo_path'])
        <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rl">
          <label class="form-check-label" for="rl">Hapus logo saat ini</label></div>
      @endif

      <hr>
      <h6 class="mb-2">Ikon tab browser (favicon)</h6>
      <div class="small text-muted mb-2">Opsional, PNG atau JPG maksimal 512 KB. Jika kosong, logo dipakai.</div>
      <input type="file" name="favicon" class="form-control" accept=".png,.jpg,.jpeg">
      @if($s['favicon_path'])
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_favicon" value="1" id="rf">
          <label class="form-check-label" for="rf">Hapus favicon saat ini</label></div>
      @endif
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card card-body">
      <h6 class="mb-3">Data Perusahaan</h6>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nama perusahaan</label>
          <input name="company_name" class="form-control" value="{{ old('company_name', $s['company_name']) }}" required></div>
        <div class="col-md-6"><label class="form-label">Slogan (di bawah nama)</label>
          <input name="company_tagline" class="form-control" value="{{ old('company_tagline', $s['company_tagline']) }}"></div>
        <div class="col-12"><label class="form-label">Alamat</label>
          <textarea name="company_address" class="form-control" rows="2">{{ old('company_address', $s['company_address']) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Telepon</label>
          <input name="company_phone" class="form-control" value="{{ old('company_phone', $s['company_phone']) }}"></div>
        <div class="col-md-6"><label class="form-label">Email</label>
          <input type="email" name="company_email" class="form-control" value="{{ old('company_email', $s['company_email']) }}"></div>
        <div class="col-md-6"><label class="form-label">NPWP</label>
          <input name="company_npwp" class="form-control" value="{{ old('company_npwp', $s['company_npwp']) }}"></div>
        <div class="col-md-6"><label class="form-label">Izin usaha / NIB</label>
          <input name="company_license" class="form-control" value="{{ old('company_license', $s['company_license']) }}"></div>
      </div>
    </div>

    <div class="card card-body mt-3">
      <h6 class="mb-1">Penandatangan Laporan</h6>
      <div class="small text-muted mb-3">Dicetak di bagian bawah Neraca. Tanda tangan dibubuhkan manual setelah dicetak.</div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Disiapkan oleh (nama)</label>
          <input name="sign_prepared_name" class="form-control" value="{{ old('sign_prepared_name', $s['sign_prepared_name']) }}"></div>
        <div class="col-md-6"><label class="form-label">Jabatan</label>
          <input name="sign_prepared_title" class="form-control" value="{{ old('sign_prepared_title', $s['sign_prepared_title']) }}"></div>
        <div class="col-md-6"><label class="form-label">Disetujui oleh (nama)</label>
          <input name="sign_approved_name" class="form-control" value="{{ old('sign_approved_name', $s['sign_approved_name']) }}"></div>
        <div class="col-md-6"><label class="form-label">Jabatan</label>
          <input name="sign_approved_title" class="form-control" value="{{ old('sign_approved_title', $s['sign_approved_title']) }}"></div>
      </div>
    </div>
  </div>

  <div class="col-12"><button class="btn btn-primary">Simpan Perubahan</button></div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('logoInput').addEventListener('change', function () {
  const f = this.files[0]; if (!f) return;
  const rd = new FileReader();
  rd.onload = e => {
    const img = document.getElementById('logoPrev');
    img.src = e.target.result; img.style.display = 'block';
    const em = document.getElementById('logoEmpty'); if (em) em.style.display = 'none';
  };
  rd.readAsDataURL(f);
});
</script>
@endpush