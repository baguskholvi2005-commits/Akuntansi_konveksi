@php
  $t = $app['theme'];
  $coInit = mb_strtoupper(mb_substr($app['name'], 0, 1));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk - {{ $app['name'] }}</title>
  @if($app['favicon_url'])<link rel="icon" href="{{ $app['favicon_url'] }}">@endif
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="{{ asset('css/sia.css') }}?v={{ @filemtime(public_path('css/sia.css')) }}" rel="stylesheet">
  <style>
    :root{ --brand:{{ $t['brand'] }}; --accent:{{ $t['accent'] }}; }
    body{ background:#fff; }
    .auth{ display:grid; grid-template-columns:1.1fr 1fr; min-height:100vh; }

    /* ===== panel kiri ===== */
    .auth-left{ position:relative; overflow:hidden; color:#fff; padding:56px 64px; display:flex; flex-direction:column; justify-content:space-between; gap:32px;
      background:
        radial-gradient(900px 500px at 15% 0%, color-mix(in srgb,var(--accent) 22%, transparent), transparent 60%),
        linear-gradient(160deg, color-mix(in srgb,var(--brand) 55%, #05080F), #05080F 85%); }
    .auth-left::before{ content:''; position:absolute; inset:0; pointer-events:none; opacity:.5;
      background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);
      background-size:36px 36px; }
    .stitch{ position:absolute; inset:0; width:100%; height:100%; opacity:.28; pointer-events:none; }
    .auth-left > *:not(.stitch){ position:relative; z-index:1; }

    .logo{ width:52px; height:52px; border-radius:14px; display:grid; place-items:center; overflow:hidden; font-weight:700; font-size:22px;
      background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.16); }
    .logo img{ width:100%; height:100%; object-fit:contain; background:#fff; }
    .logo.initial{ background:var(--accent); border-color:transparent; }
    .eyebrow{ display:flex; align-items:center; gap:10px; color:var(--accent); font-size:12px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; }
    .eyebrow::before{ content:''; width:28px; height:2px; background:var(--accent); }
    .headline{ font-size:clamp(2rem,3.4vw,3.1rem); font-weight:700; line-height:1.08; letter-spacing:-.03em; max-width:560px; margin:16px 0; }
    .lead2{ color:rgba(255,255,255,.72); max-width:520px; font-size:1.02rem; line-height:1.6; }
    .fcard{ height:100%; padding:18px; border-radius:16px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); backdrop-filter:blur(6px); }
    .fcard i{ width:38px; height:38px; display:grid; place-items:center; border-radius:10px; margin-bottom:12px; font-size:18px;
      background:color-mix(in srgb,var(--accent) 20%, transparent); color:var(--accent); }
    .fcard b{ display:block; margin-bottom:4px; }
    .fcard span{ font-size:13px; color:rgba(255,255,255,.62); line-height:1.5; }

    /* ===== panel kanan ===== */
    .auth-right{ display:flex; align-items:center; justify-content:center; padding:40px 32px; background:#FAFBFD; }
    .login-card{ width:100%; max-width:440px; }
    .pill{ display:inline-block; padding:5px 12px; border-radius:8px; background:#EEF1F8; color:#475569; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .login-card h1{ font-size:2rem; font-weight:700; letter-spacing:-.03em; margin:14px 0 6px; }
    .field{ position:relative; }
    .field > i{ position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:17px; pointer-events:none; }
    .field .form-control{ padding-left:44px; height:50px; border-radius:12px; background:#fff; }
    .field .eye{ position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; color:#94A3B8; padding:8px 10px; }
    .field .eye:hover{ color:var(--ink); }
    .btn-enter{ height:52px; border-radius:12px; font-size:1rem; display:flex; align-items:center; justify-content:center; gap:10px;
      background:var(--brand); border:0; color:#fff; font-weight:600; }
    .btn-enter:hover{ background:color-mix(in srgb,var(--brand) 85%,#000); color:#fff; }
    .err{ background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; border-radius:12px; padding:12px 14px; display:flex; gap:10px; align-items:flex-start; }
    .err .x{ margin-left:auto; border:0; background:none; color:#991B1B; padding:0 2px; font-size:18px; line-height:1; }

    @media (max-width:991.98px){
      .auth{ grid-template-columns:1fr; }
      .auth-left{ padding:28px 24px; gap:20px; }
      .auth-left .hide-m{ display:none; }
      .headline{ font-size:1.8rem; margin:10px 0; }
      .auth-right{ padding:28px 20px 40px; align-items:flex-start; }
    }
  </style>
</head>
<body>
<div class="auth">

  <section class="auth-left">
    <svg class="stitch" viewBox="0 0 700 900" preserveAspectRatio="xMidYMid slice" fill="none">
      <path d="M-20 760 C 180 620, 300 860, 480 700 S 720 560, 720 560" stroke="#fff" stroke-width="2" stroke-dasharray="7 10" stroke-linecap="round"/>
      <path d="M-20 830 C 200 700, 340 900, 520 760 S 740 640, 740 640" stroke="#fff" stroke-width="1.5" stroke-dasharray="4 9" stroke-linecap="round"/>
      <circle cx="590" cy="150" r="100" stroke="#fff" stroke-width="1.5" stroke-dasharray="5 9"/>
      <circle cx="590" cy="150" r="60" stroke="#fff" stroke-width="1.5" stroke-dasharray="3 8"/>
    </svg>

    <div>
      <div class="d-flex align-items-center gap-3">
        <div class="logo {{ $app['logo_url'] ? '' : 'initial' }}">
          @if($app['logo_url'])<img src="{{ $app['logo_url'] }}" alt="Logo">@else{{ $coInit }}@endif
        </div>
        <div>
          <div class="fw-bold fs-5">{{ $app['name'] }}</div>
          <div class="small" style="color:rgba(255,255,255,.65)">{{ $app['tagline'] }}</div>
        </div>
      </div>
    </div>

    <div>
      <div class="eyebrow">Akuntansi tekstil dan konveksi</div>
      <div class="headline">Kendalikan stok, harga pokok, dan pembukuan dalam satu sistem</div>
      <div class="lead2 hide-m">Dari pembelian bahan, produksi, dan penjualan sampai laporan keuangan: semua tercatat sebagai jurnal otomatis dan saling cocok.</div>
    </div>

    <div class="row g-3 hide-m">
      <div class="col-4"><div class="fcard"><i class="bi bi-journal-check"></i><b>Jurnal otomatis</b>
        <span>Setiap transaksi langsung menjadi jurnal yang seimbang.</span></div></div>
      <div class="col-4"><div class="fcard"><i class="bi bi-boxes"></i><b>Persediaan akurat</b>
        <span>Kartu stok dan harga pokok rata-rata selalu cocok dengan jurnal.</span></div></div>
      <div class="col-4"><div class="fcard"><i class="bi bi-bar-chart-line"></i><b>Laporan lengkap</b>
        <span>Laba rugi, neraca, arus kas, dan perubahan modal siap cetak.</span></div></div>
    </div>

    <div class="small" style="color:rgba(255,255,255,.5)">&copy; {{ date('Y') }} {{ $app['name'] }}</div>
  </section>

  <section class="auth-right">
    <div class="login-card">
      <span class="pill">Portal masuk</span>
      <h1>Selamat datang kembali</h1>
      <p class="text-muted mb-4">Masuk untuk mengakses pembukuan dan persediaan {{ $app['name'] }}.</p>

      @if($errors->any())
        <div class="err mb-3" id="err" role="alert">
          <i class="bi bi-exclamation-circle mt-1"></i>
          <div>{{ $errors->first() }}</div>
          <button type="button" class="x" aria-label="Tutup" onclick="document.getElementById('err').remove()">&times;</button>
        </div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <div class="field">
            <i class="bi bi-envelope"></i>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                   placeholder="nama@perusahaan.com" required autofocus autocomplete="username">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Kata sandi</label>
          <div class="field">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" id="pw" class="form-control" placeholder="Kata sandi Anda" required autocomplete="current-password">
            <button type="button" class="eye" id="pwToggle" tabindex="-1" aria-label="Tampilkan kata sandi"><i class="bi bi-eye" style="position:static;transform:none;color:inherit"></i></button>
          </div>
        </div>
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="remember" id="remember">
          <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
        </div>
        <button class="btn-enter w-100">Masuk ke Dashboard <i class="bi bi-arrow-right"></i></button>
      </form>

      <p class="text-muted small text-center mt-4 mb-0">Lupa kata sandi? Hubungi admin untuk mengatur ulang.</p>
    </div>
  </section>
</div>

<script>
  document.getElementById('pwToggle').addEventListener('click', function () {
    const pw = document.getElementById('pw'), show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    this.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '" style="position:static;transform:none;color:inherit"></i>';
  });
</script>
</body>
</html>