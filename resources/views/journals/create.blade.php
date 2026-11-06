@extends('layouts.sia')
@section('title','Jurnal Manual')
@section('content')
<h4>Jurnal Manual</h4>
<div class="alert alert-info py-2">Dipakai untuk penyesuaian, misalnya beban yang belum tercatat. Akun persediaan
  tidak tersedia di sini. Jurnal harus seimbang sebelum bisa disimpan.</div>

<form method="POST" action="{{ route('journals.store') }}" class="card card-body">
  @csrf
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Tanggal</label>
      <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-9"><label class="form-label">Keterangan</label>
      <input name="description" class="form-control" value="{{ old('description') }}" placeholder="Contoh: Beban lain-lain bulan November" required></div>
  </div>

  <table class="table table-bordered" id="tbl">
    <thead><tr><th>Akun</th><th width="180">Debit</th><th width="180">Kredit</th><th>Memo</th><th width="50"></th></tr></thead>
    <tbody></tbody>
    <tfoot>
      <tr><th class="text-end">Total</th><th class="num" id="td">0</th><th class="num" id="tc">0</th>
          <th colspan="2" id="status"></th></tr>
    </tfoot>
  </table>
  <div class="mb-3"><button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">+ Tambah Baris</button></div>

  <div><button class="btn btn-primary" id="btnSave" disabled>Posting Jurnal</button>
       <a href="{{ route('journals.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection

@push('scripts')
<script>
const ACCS = @json($accounts->map(fn($a) => ['code' => $a->code, 'label' => $a->code.' - '.$a->name]));
let idx = 0;

function addRow() {
  const opts = ACCS.map(a => `<option value="${a.code}">${a.label}</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="lines[${idx}][account]" class="form-select" required>${opts}</select></td>
    <td><input type="number" step="0.01" min="0" name="lines[${idx}][debit]" class="form-control deb"></td>
    <td><input type="number" step="0.01" min="0" name="lines[${idx}][credit]" class="form-control cre"></td>
    <td><input name="lines[${idx}][memo]" class="form-control" maxlength="200"></td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();calc()">×</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr);
  tr.querySelectorAll('input').forEach(el => el.addEventListener('input', calc));
  idx++;
  calc();
}

function calc() {
  let d = 0, c = 0, rows = 0;
  document.querySelectorAll('#tbl tbody tr').forEach(tr => {
    d += parseFloat(tr.querySelector('.deb').value) || 0;
    c += parseFloat(tr.querySelector('.cre').value) || 0;
    rows++;
  });
  d = Math.round(d * 100) / 100;
  c = Math.round(c * 100) / 100;
  const diff = Math.round((d - c) * 100) / 100;

  document.getElementById('td').textContent = d.toLocaleString('id-ID');
  document.getElementById('tc').textContent = c.toLocaleString('id-ID');

  const ok = d > 0 && diff === 0 && rows >= 2;
  const st = document.getElementById('status');
  st.textContent = ok ? 'Seimbang' : 'Selisih: ' + diff.toLocaleString('id-ID');
  st.className = ok ? 'text-success' : 'text-danger';
  document.getElementById('btnSave').disabled = !ok;
}

addRow();
addRow();
</script>
@endpush