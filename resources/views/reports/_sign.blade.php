@php $S = \App\Models\Setting::values(); @endphp
<div class="card card-body mt-3">
  <div class="row g-3 text-center">
    <div class="col-6"><div class="small text-muted">Disiapkan oleh</div><div style="height:60px"></div>
      <div class="fw-semibold border-top pt-1 mx-4">{{ $S['sign_prepared_name'] ?: '........................' }}</div>
      <div class="small text-muted">{{ $S['sign_prepared_title'] }}</div></div>
    <div class="col-6"><div class="small text-muted">Disetujui oleh</div><div style="height:60px"></div>
      <div class="fw-semibold border-top pt-1 mx-4">{{ $S['sign_approved_name'] ?: '........................' }}</div>
      <div class="small text-muted">{{ $S['sign_approved_title'] }}</div></div>
  </div>
  <div class="small text-muted text-center mt-3">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }}</div>
</div>