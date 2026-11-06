@extends('layouts.sia')
@section('title','Pembelian Baru')
@section('content')
<div class="mb-3">
  <div class="small text-muted"><a href="{{ route('purchases.index') }}">Pembelian</a> / Buat Pembelian Baru</div>
  <h4 class="mb-1">Faktur Pembelian Bahan Baku Baru</h4>
  <span class="badge bg-secondary-subtle text-secondary-emphasis">STATUS: DRAFT BELUM DIPOSTING</span>
</div>

<form method="POST" action="{{ route('purchases.store') }}">
@csrf
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card card-body mb-3">
      <h6 class="mb-3">1. Informasi Umum Transaksi</h6>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nomor Faktur</label>
          <input class="form-control" value="Otomatis saat disimpan" disabled></div>
        <div class="col-md-6"><label class="form-label">Tanggal Transaksi</label>
          <input type="date" name="date" id="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
        <div class="col-12"><label class="form-label">Pemasok</label>
          <select name="supplier_id" class="form-select" required>
            <option value="">-- pilih pemasok --</option>
            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id')==$s->id)>{{ $s->name }} ({{ $s->code }})</option>@endforeach
          </select></div>
        <div class="col-md-5"><label class="form-label d-block">Metode Pembayaran</label>
          <div class="btn-group w-100">
            <input type="radio" class="btn-check" name="payment_method" id="pm1" value="tunai" {{ old('payment_method','tunai')=='tunai' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="pm1"><i class="bi bi-cash"></i> Tunai / Kas</label>
            <input type="radio" class="btn-check" name="payment_method" id="pm2" value="kredit" {{ old('payment_method')=='kredit' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="pm2"><i class="bi bi-credit-card"></i> Kredit (Tempo)</label>
          </div></div>
        <div class="col-md-3" id="termBox"><label class="form-label">Termin</label>
          <select id="term" class="form-select">
            <option value="7">Net 7</option><option value="14">Net 14</option><option value="30" selected>Net 30</option><option value="60">Net 60</option>
          </select></div>
        <div class="col-md-4" id="dueBox"><label class="form-label">Jatuh Tempo</label>
          <input type="date" name="due_date" id="due" class="form-control" value="{{ old('due_date') }}"></div>
        <div class="col-12"><label class="form-label">Catatan / Referensi</label>
          <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
      </div>
    </div>

    <div class="card card-body">
      <div class="d-flex justify-content-between mb-3"><h6 class="mb-0">2. Rincian Item Bahan Baku</h6>
        <span class="badge bg-info-subtle text-info-emphasis" id="itemCount">0 item</span></div>
      <div class="table-responsive">
      <table class="table align-middle" id="tbl">
        <thead><tr><th>Bahan Baku</th><th width="110">Qty</th><th width="160">Harga Satuan</th><th width="90">Disc %</th><th width="150" class="num">Subtotal</th><th width="40"></th></tr></thead>
        <tbody></tbody>
      </table></div>
      <div><button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()"><i class="bi bi-plus-circle"></i> Tambah Baris Bahan Baku</button></div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card card-body mb-3">
      <h6 class="mb-3">Ringkasan Biaya</h6>
      <div class="d-flex justify-content-between mb-2"><span>Subtotal Item</span><b id="sSub">Rp 0</b></div>
      <div class="d-flex justify-content-between align-items-center mb-2"><span>Diskon Faktur</span>
        <input type="number" step="0.01" min="0" name="discount" id="disc" class="form-control form-control-sm text-end" style="max-width:140px" value="{{ old('discount',0) }}"></div>
      <div class="d-flex justify-content-between align-items-center mb-3"><span>Ongkos Kirim</span>
        <input type="number" step="0.01" min="0" name="shipping_cost" id="ship" class="form-control form-control-sm text-end" style="max-width:140px" value="{{ old('shipping_cost',0) }}"></div>
      <div class="rounded-3 p-3 text-white mb-2" style="background:var(--brand)">
        <div class="small opacity-75">TOTAL PEMBELIAN</div><div class="fs-4 fw-bold" id="sTotal">Rp 0</div>
        <div class="small" id="sWho">Membebani Kas</div></div>
      <div class="small text-muted mb-3">Diskon dan ongkir dibagi proporsional ke harga pokok tiap barang saat diposting.</div>
      <button class="btn btn-primary w-100 mb-2"><i class="bi bi-save"></i> Simpan sebagai Draft</button>
      <a href="{{ route('purchases.index') }}" class="btn btn-light border w-100">Batal</a>
      <div class="small text-muted mt-2">Posting dilakukan di halaman detail oleh admin atau keuangan.</div>
    </div>

    <div class="card card-body">
      <div class="d-flex justify-content-between mb-2"><h6 class="mb-0">Pratinjau Jurnal</h6><span class="badge bg-success" id="bal">SEIMBANG</span></div>
      <table class="table table-sm mb-0"><tbody>
        <tr><td>(D) 1301 Persediaan Bahan Baku</td><td class="num" id="jD">0</td></tr>
        <tr><td id="jKname">(K) 1101 Kas</td><td class="num" id="jK">0</td></tr>
      </tbody></table>
    </div>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
const ITEMS = @json($items->map(fn($i) => ['id' => $i->id, 'label' => $i->code.' - '.$i->name.' ('.$i->unit->name.')']));
const fmt = v => Math.round(v).toLocaleString('id-ID');
let idx = 0;

function addRow() {
  const opts = ITEMS.map(i => `<option value="${i.id}">${i.label}</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="items[${idx}][item_id]" class="form-select" required>${opts}</select></td>
    <td><input type="number" step="0.01" min="0.01" name="items[${idx}][qty]" class="form-control qty" required></td>
    <td><input type="number" step="0.01" min="0" name="items[${idx}][unit_price]" class="form-control price" required></td>
    <td><input type="number" step="0.01" min="0" max="100" name="items[${idx}][discount_pct]" class="form-control dp" value="0"></td>
    <td class="num sub">0</td>
    <td><button type="button" class="btn btn-sm btn-light border" onclick="this.closest('tr').remove();calc()">&times;</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr);
  tr.querySelectorAll('input').forEach(el => el.addEventListener('input', calc));
  idx++; calc();
}

function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
function setDue() {
  const d = document.getElementById('date').value; if (!d) return;
  const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + parseInt(document.getElementById('term').value));
  document.getElementById('due').value = ymd(x);
}
function toggle() {
  const kredit = document.getElementById('pm2').checked;
  ['termBox','dueBox'].forEach(id => document.getElementById(id).style.display = kredit ? '' : 'none');
  document.getElementById('due').required = kredit;
  if (kredit && !document.getElementById('due').value) setDue();
  document.getElementById('sWho').textContent = kredit ? 'Membebani Utang Usaha' : 'Membebani Kas';
  document.getElementById('jKname').textContent = kredit ? '(K) 2101 Utang Usaha' : '(K) 1101 Kas';
}

function calc() {
  let sub = 0, n = 0;
  document.querySelectorAll('#tbl tbody tr').forEach(tr => {
    const s = (parseFloat(tr.querySelector('.qty').value)||0) * (parseFloat(tr.querySelector('.price').value)||0)
              * (1 - (parseFloat(tr.querySelector('.dp').value)||0)/100);
    tr.querySelector('.sub').textContent = fmt(s); sub += s; n++;
  });
  const disc = parseFloat(document.getElementById('disc').value)||0, ship = parseFloat(document.getElementById('ship').value)||0;
  const total = sub - disc + ship;
  document.getElementById('itemCount').textContent = n + ' item';
  document.getElementById('sSub').textContent = 'Rp ' + fmt(sub);
  document.getElementById('sTotal').textContent = 'Rp ' + fmt(total);
  document.getElementById('jD').textContent = fmt(total); document.getElementById('jK').textContent = fmt(total);
  const bad = disc > sub;
  const b = document.getElementById('bal'); b.textContent = bad ? 'DISKON TERLALU BESAR' : 'SEIMBANG'; b.className = 'badge ' + (bad ? 'bg-danger' : 'bg-success');
}

document.querySelectorAll('input[name=payment_method]').forEach(e => e.addEventListener('change', toggle));
document.getElementById('term').addEventListener('change', setDue);
document.getElementById('date').addEventListener('change', () => { if (document.getElementById('pm2').checked) setDue(); });
['disc','ship'].forEach(id => document.getElementById(id).addEventListener('input', calc));
addRow(); toggle();
</script>
@endpush