@extends('layouts.sia')
@section('title','Tampilan')
@section('content')
<h4 class="mb-3">Pengaturan</h4>
@include('settings._tabs')

<div class="row g-4">
  <div class="col-lg-7">
    <form method="POST" action="{{ route('settings.appearance.save') }}" class="card card-body">
      @csrf
      <h6>Warna sidebar</h6>
      <div class="d-flex flex-wrap gap-2 mb-2" id="sbSw"></div>
      <div class="input-group mb-4" style="max-width:260px">
        <input type="color" id="sbPick" class="form-control form-control-color">
        <input name="sidebar_color" id="sbHex" class="form-control" maxlength="7" value="{{ old('sidebar_color', $s['sidebar_color']) }}">
      </div>

      <h6>Warna aksen</h6>
      <div class="d-flex flex-wrap gap-2 mb-2" id="acSw"></div>
      <div class="input-group mb-4" style="max-width:260px">
        <input type="color" id="acPick" class="form-control form-control-color">
        <input name="accent_color" id="acHex" class="form-control" maxlength="7" value="{{ old('accent_color', $s['accent_color']) }}">
      </div>

      <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" name="sidebar_collapsed" value="1" id="col"
               {{ old('sidebar_collapsed', $s['sidebar_collapsed']) == '1' ? 'checked' : '' }}>
        <label class="form-check-label" for="col">Sidebar ringkas (hanya ikon) sebagai bawaan</label>
        <div class="small text-muted">Tiap pengguna tetap bisa melebarkan atau meringkas sendiri lewat tombol di sidebar.</div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary">Simpan</button>
        <button type="submit" form="resetForm" class="btn btn-outline-secondary"
                onclick="return confirm('Kembalikan warna dan tampilan ke bawaan?')">Kembalikan ke Bawaan</button>
      </div>
    </form>
    <form id="resetForm" method="POST" action="{{ route('settings.appearance.reset') }}">@csrf</form>
  </div>

  <div class="col-lg-5">
    <div class="card card-body">
      <h6 class="mb-3">Pratinjau langsung</h6>
      <div class="mini-sb" id="prev">
        <div class="m-name">{{ \App\Models\Setting::val('company_name') }}</div>
        <div class="m-item on">Dashboard</div>
        <div class="m-lbl">TRANSAKSI</div>
        <div class="m-item">Pembelian</div>
        <div class="m-item">Produksi</div>
        <div class="m-lbl">LAPORAN</div>
        <div class="m-item">Laba Rugi</div>
      </div>
      <div class="mt-3"><button type="button" class="btn btn-primary btn-sm">Contoh tombol</button></div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const SB = ['#1E2A5A','#0F172A','#064E3B','#334155','#7F1D1D','#1F2937','#F8FAFC'];
const AC = ['#14B8A6','#6366F1','#10B981','#F59E0B','#F43F5E','#0EA5E9'];

const hex = (c,f) => /^#[0-9a-fA-F]{6}$/.test(c) ? c.toUpperCase() : f;
const rgb = h => [1,3,5].map(i => parseInt(h.substr(i,2),16));
const mix = (a,b,t) => { const x = rgb(a), y = rgb(b);
  return '#' + x.map((v,i) => Math.round(v*(1-t)+y[i]*t).toString(16).padStart(2,'0')).join('').toUpperCase(); };
const lum = h => { const [r,g,b] = rgb(h); return (0.2126*r+0.7152*g+0.0722*b)/255; };

function theme(bg, ac) {
  bg = hex(bg,'#1E2A5A'); ac = hex(ac,'#14B8A6');
  const l = lum(bg), light = l > 0.6, W = '#FFFFFF', K = '#000000';
  const brand = light ? mix(ac,K,.4) : (l > .35 ? mix(bg,K,.3) : bg);
  return {
    brand, bg, ac,
    title: light ? '#0F172A' : W,
    text:  light ? mix(bg,K,.65) : mix(bg,W,.72),
    muted: light ? mix(bg,K,.45) : mix(bg,W,.5),
    hover: light ? mix(bg,K,.05) : mix(bg,W,.08),
    line:  light ? mix(bg,K,.08) : mix(bg,W,.12),
    active: light ? mix(ac,K,.35) : W
  };
}

const sbHex = document.getElementById('sbHex'), acHex = document.getElementById('acHex');
const sbPick = document.getElementById('sbPick'), acPick = document.getElementById('acPick');
const prev = document.getElementById('prev');

function apply() {
  const t = theme(sbHex.value, acHex.value), s = prev.style;
  s.setProperty('--sb-bg', t.bg); s.setProperty('--sb-text', t.text); s.setProperty('--sb-title', t.title);
  s.setProperty('--sb-muted', t.muted); s.setProperty('--sb-active', t.active); s.setProperty('--accent', t.ac);
  prev.parentElement.style.setProperty('--brand', t.brand);
  prev.parentElement.style.setProperty('--accent', t.ac);
  sbPick.value = hex(sbHex.value,'#1E2A5A').toLowerCase();
  acPick.value = hex(acHex.value,'#14B8A6').toLowerCase();
  document.querySelectorAll('#sbSw .swatch').forEach(b => b.classList.toggle('sel', b.dataset.c === hex(sbHex.value,'')));
  document.querySelectorAll('#acSw .swatch').forEach(b => b.classList.toggle('sel', b.dataset.c === hex(acHex.value,'')));
}

function swatches(boxId, list, input) {
  const box = document.getElementById(boxId);
  list.forEach(c => {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'swatch'; b.style.background = c; b.dataset.c = c; b.title = c;
    b.onclick = () => { input.value = c; apply(); };
    box.appendChild(b);
  });
}
swatches('sbSw', SB, sbHex); swatches('acSw', AC, acHex);
sbHex.addEventListener('input', apply); acHex.addEventListener('input', apply);
sbPick.addEventListener('input', () => { sbHex.value = sbPick.value.toUpperCase(); apply(); });
acPick.addEventListener('input', () => { acHex.value = acPick.value.toUpperCase(); apply(); });
apply();
</script>
@endpush