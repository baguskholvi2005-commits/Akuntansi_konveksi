@extends('layouts.sia')
@section('title','Produksi Baru')
@section('content')
<h4>Buat Produksi Baru</h4>
<form method="POST" action="{{ route('productions.store') }}" class="card card-body">
  @csrf
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Tanggal Mulai</label>
      <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-6"><label class="form-label">Produk yang Dibuat</label>
      <select name="item_id" id="prod" class="form-select" required>
        <option value="">-- pilih produk --</option>
        @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('item_id')==$p->id)>{{ $p->code }} - {{ $p->name }}</option>@endforeach
      </select></div>
    <div class="col-md-3"><label class="form-label">Jumlah Produksi</label>
      <input type="number" step="0.01" min="0.01" name="qty" id="qty" class="form-control" value="{{ old('qty') }}" required></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Kebutuhan Bahan Baku</h6>
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillRecipe()"><i class="bi bi-magic"></i> Isi dari resep</button>
  </div>
  <div class="small text-muted mb-2" id="recipeMsg">Pilih produk dan jumlah, lalu klik "Isi dari resep" (resep diatur admin di Data Master). Jumlah tetap bisa diubah.</div>
  <table class="table align-middle" id="tbl">
    <thead><tr><th>Bahan Baku (stok tersedia)</th><th width="180">Jumlah Dipakai</th><th width="50"></th></tr></thead>
    <tbody></tbody>
  </table>
  <div class="mb-3"><button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">+ Tambah Bahan</button></div>

  <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control">{{ old('notes') }}</textarea></div>
  <div class="alert alert-info">Biaya tenaga kerja dan overhead diisi nanti, saat produksi diselesaikan.</div>
  <div><button class="btn btn-primary">Simpan Draft</button> <a href="{{ route('productions.index') }}" class="btn btn-light border">Batal</a></div>
</form>
@endsection

@push('scripts')
<script>
const MATS = @json($materials->map(fn($m) => ['id' => $m->id, 'label' => $m->code.' - '.$m->name.' (stok '.rtrim(rtrim(number_format($m->stock_qty,2,'.',''),'0'),'.').' '.$m->unit->name.')']));
const RECIPES = @json($recipes);
let idx = 0;

function addRow(mid, qty) {
  const opts = MATS.map(m => `<option value="${m.id}" ${mid == m.id ? 'selected' : ''}>${m.label}</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="materials[${idx}][item_id]" class="form-select" required>${opts}</select></td>
    <td><input type="number" step="0.01" min="0.01" name="materials[${idx}][qty]" class="form-control" value="${qty ?? ''}" required></td>
    <td><button type="button" class="btn btn-sm btn-light border" onclick="this.closest('tr').remove()">&times;</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr); idx++;
}

function fillRecipe() {
  const p = document.getElementById('prod').value, q = parseFloat(document.getElementById('qty').value) || 0;
  const msg = document.getElementById('recipeMsg');
  if (!p || q <= 0) { msg.textContent = 'Pilih produk dan isi jumlah produksi lebih dulu.'; return; }
  const r = RECIPES[p];
  if (!r || !r.length) { msg.textContent = 'Produk ini belum punya resep. Isi bahan manual, atau minta admin mengatur resep.'; return; }
  document.querySelector('#tbl tbody').innerHTML = '';
  r.forEach(l => addRow(l.m, Math.round(q * l.q * (1 + l.w / 100) * 100) / 100));
  msg.textContent = 'Bahan diisi dari resep (sudah termasuk susut). Periksa dan ubah bila perlu.';
}
addRow();
</script>
@endpush