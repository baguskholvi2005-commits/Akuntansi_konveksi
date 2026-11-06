<form class="row g-2 mb-3 no-print">
  <div class="col-md-3"><label class="form-label small mb-0">Dari tanggal (kosong = sejak awal)</label>
    <input type="date" name="from" value="{{ $from }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label small mb-0">Sampai tanggal</label>
    <input type="date" name="to" value="{{ $to }}" class="form-control"></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tampilkan</button></div>
  <div class="col-md-2 d-flex align-items-end">
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary w-100">Cetak</button></div>
  <div class="col-md-2 d-flex align-items-end">@include('partials.export')</div>
</form>