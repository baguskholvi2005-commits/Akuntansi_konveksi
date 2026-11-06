@extends('layouts.sia')
@section('title','Atur Resep')
@section('content')
<h4>Resep: {{ $item->name }}</h4>
<div class="text-muted mb-3">Isi kebutuhan bahan untuk <b>1 {{ $item->unit->name }}</b>. Susut dalam persen ditambahkan saat produksi.</div>
<form method="POST" action="{{ route('recipes.update',$item) }}" class="card card-body">
  @csrf @method('PUT')
  <table class="table align-middle" id="tbl">
    <thead><tr><th>Bahan Baku</th><th width="190">Jumlah per unit</th><th width="140">Susut %</th><th width="40"></th></tr></thead>
    <tbody></tbody>
  </table>
  <div class="mb-3"><button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">+ Tambah Bahan</button></div>
  <div><button class="btn btn-primary">Simpan Resep</button> <a href="{{ route('recipes.index') }}" class="btn btn-light border">Batal</a></div>
</form>
@endsection

@push('scripts')
<script>
const MATS = @json($materials->map(fn($m) => ['id' => $m->id, 'label' => $m->code.' - '.$m->name.' ('.$m->unit->name.')']));
const LINES = @json($lines->map(fn($l) => ['m' => $l->material_id, 'q' => (float) $l->qty_per_unit, 'w' => (float) $l->waste_pct]));
let idx = 0;
function addRow(l) {
  const opts = MATS.map(m => `<option value="${m.id}" ${l && l.m == m.id ? 'selected' : ''}>${m.label}</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="lines[${idx}][material_id]" class="form-select" required>${opts}</select></td>
    <td><input type="number" step="0.0001" min="0.0001" name="lines[${idx}][qty_per_unit]" class="form-control" value="${l ? l.q : ''}" required></td>
    <td><input type="number" step="0.01" min="0" max="100" name="lines[${idx}][waste_pct]" class="form-control" value="${l ? l.w : 0}"></td>
    <td><button type="button" class="btn btn-sm btn-light border" onclick="this.closest('tr').remove()">&times;</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr); idx++;
}
LINES.forEach(addRow);
</script>
@endpush