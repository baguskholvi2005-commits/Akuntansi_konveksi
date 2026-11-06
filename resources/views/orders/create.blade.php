@extends('layouts.sia')
@section('title','Pesanan Baru')
@section('content')
<h4>Pesanan Baru</h4>
<form method="POST" action="{{ route('orders.store') }}" class="card card-body">
  @csrf
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Tanggal</label>
      <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required></div>
    <div class="col-md-5"><label class="form-label">Pelanggan</label>
      <select name="customer_id" class="form-select" required>
        <option value="">-- pilih --</option>
        @foreach($customers as $c)
          <option value="{{ $c->id }}" @selected(old('customer_id')==$c->id)>{{ $c->code }} - {{ $c->name }}</option>
        @endforeach
      </select></div>
    <div class="col-md-4"><label class="form-label">Jenis Penjualan</label>
      <select name="payment_type" class="form-select" required>
        <option value="tunai">Tunai (harus lunas sebelum kirim)</option>
        <option value="kredit">Kredit (boleh jadi piutang)</option>
      </select></div>
  </div>

  <table class="table table-bordered" id="tbl">
    <thead><tr><th>Produk</th><th width="140">Jumlah</th><th width="180">Harga Satuan</th><th width="180">Subtotal</th><th width="50"></th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="num" id="grand">0</th><th></th></tr></tfoot>
  </table>
  <div class="mb-3"><button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">+ Tambah Produk</button></div>

  <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control">{{ old('notes') }}</textarea></div>
  <div class="alert alert-info">Uang muka (DP) dicatat di halaman detail pesanan setelah pesanan disimpan.</div>
  <div><button class="btn btn-primary">Simpan Pesanan</button>
       <a href="{{ route('orders.index') }}" class="btn btn-secondary">Batal</a></div>
</form>
@endsection

@push('scripts')
<script>
const PRODUCTS = @json($products->map(fn($p) => [
  'id' => $p->id,
  'label' => $p->code.' - '.$p->name.' (stok '.(float) $p->stock_qty.' '.$p->unit->name.')',
  'price' => (float) $p->sale_price,
]));
let idx = 0;

function addRow() {
  const opts = PRODUCTS.map(p => `<option value="${p.id}">${p.label}</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="items[${idx}][item_id]" class="form-select prod" required>${opts}</select></td>
    <td><input type="number" step="0.01" min="0.01" name="items[${idx}][qty]" class="form-control qty" required></td>
    <td><input type="number" step="0.01" min="0.01" name="items[${idx}][unit_price]" class="form-control price" required></td>
    <td class="num sub">0</td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();calc()">×</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr);

  const sel = tr.querySelector('.prod');
  sel.addEventListener('change', () => {
    const p = PRODUCTS.find(x => x.id == sel.value);
    tr.querySelector('.price').value = p ? p.price : '';
    calc();
  });
  tr.querySelectorAll('input').forEach(el => el.addEventListener('input', calc));
  sel.dispatchEvent(new Event('change'));   // isi harga awal otomatis
  idx++;
}

function calc() {
  let total = 0;
  document.querySelectorAll('#tbl tbody tr').forEach(tr => {
    const s = (parseFloat(tr.querySelector('.qty').value) || 0) * (parseFloat(tr.querySelector('.price').value) || 0);
    tr.querySelector('.sub').textContent = s.toLocaleString('id-ID');
    total += s;
  });
  document.getElementById('grand').textContent = total.toLocaleString('id-ID');
}

addRow();
</script>
@endpush