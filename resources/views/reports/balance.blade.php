@extends('layouts.sia')
@section('title','Neraca')
@section('content')
@php
  $f  = fn($v) => number_format($v, 0, ',', '.');
  $fm = fn($v) => $v < 0 ? '(' . number_format(abs($v), 0, ',', '.') . ')' : number_format($v, 0, ',', '.');
  $S  = \App\Models\Setting::values();
  $d  = fn($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
  $cols = $compare ? 5 : 2;

  // Selisih dan persen terhadap pembanding
  $delta = function ($cur, $prev) use ($fm) {
      $diff = round($cur - $prev, 2);
      if (abs($diff) < 0.005) return '<span class="text-muted">0</span>';
      $pct = abs($prev) < 0.005 ? '' : '<div class="small text-muted">(' . ($diff > 0 ? '+' : '') . number_format($diff / abs($prev) * 100, 1, ',', '.') . '%)</div>';
      return '<span class="' . ($diff > 0 ? 'text-success' : 'text-danger') . '">' . ($diff > 0 ? '+' : '') . $fm($diff) . '</span>' . $pct;
  };

  // Satu bagian laporan. $title judul bagian, $rows daftar akun, $extra baris tambahan (mis. laba berjalan)
  $section = function ($title, $rows, $totalLabel, $curTotal, $prevTotal, $extra = null) use ($compare, $cols, $f, $fm, $delta) {
      $h = '<tr class="table-light"><th colspan="' . $cols . '">' . $title . '</th></tr>';
      $line = function ($label, $c, $p) use ($compare, $fm, $delta) {
          $x = '<tr><td style="padding-left:2rem">' . e($label) . '</td><td class="num">' . $fm($c) . '</td>';
          if ($compare) $x .= '<td class="num">' . $fm($p) . '</td><td class="num">' . $delta($c, $p) . '</td>';
          return $x . '</tr>';
      };
      foreach ($rows as $r) $h .= $line($r['code'] . ' ' . $r['name'], $r['cur'], $compare ? $r['prev'] : 0);
      if ($extra) $h .= $line($extra[0], $extra[1], $compare ? $extra[2] : 0);
      $h .= '<tr class="table-info"><th class="text-end">' . $totalLabel . '</th><th class="num">' . $fm($curTotal) . '</th>';
      if ($compare) $h .= '<th class="num">' . $fm($prevTotal) . '</th><th class="num">' . $delta($curTotal, $prevTotal) . '</th>';
      return $h . '</tr>';
  };
@endphp

<h4>Laporan Keuangan</h4>
@include('reports._nav')

<form class="row g-2 mb-3 no-print align-items-end">
  <div class="col-md-3"><label class="form-label small mb-0">Per tanggal</label>
    <input type="date" name="as_of" value="{{ $asOf }}" class="form-control"></div>
  <div class="col-md-3"><div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="compare" value="1" id="cmp" {{ $compare ? 'checked' : '' }}>
    <label class="form-check-label" for="cmp">Bandingkan dengan akhir bulan lalu</label></div></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
  <div class="col-md-2"><button type="button" onclick="window.print()" class="btn btn-outline-secondary w-100">Cetak</button></div>
  <div class="col-md-2">@include('partials.export')</div>
</form>

<div class="card card-body" style="max-width:900px;margin:auto">
  {{-- Kop --}}
  <div class="text-center mb-3 pb-3 border-bottom">
    <div class="fs-5 fw-bold">{{ $S['company_name'] }}</div>
    @if($S['company_address'])<div class="small text-muted">{{ $S['company_address'] }}</div>@endif
    <div class="small text-muted">
      @if($S['company_npwp'])NPWP: {{ $S['company_npwp'] }}@endif
      @if($S['company_npwp'] && $S['company_license']) &middot; @endif
      @if($S['company_license']){{ $S['company_license'] }}@endif
    </div>
  </div>

  <div class="text-center mb-3">
    <div class="text-uppercase small text-muted" style="letter-spacing:.1em">Laporan Keuangan</div>
    <h5 class="fw-bold mb-1">LAPORAN POSISI KEUANGAN (NERACA)</h5>
    <div class="text-muted">Per {{ $d($asOf) }}@if($compare) dan {{ $d($prevDate) }}@endif (dinyatakan dalam Rupiah)</div>
    @if($data['balanced'])<span class="badge bg-success mt-1">SEIMBANG</span>
    @else<span class="badge bg-danger mt-1">TIDAK SEIMBANG, periksa jurnal</span>@endif
  </div>

  <div class="table-responsive">
  <table class="table table-bordered bg-white mb-0">
    <thead><tr><th>Akun</th><th class="num">{{ $d($asOf) }}</th>
      @if($compare)<th class="num">{{ $d($prevDate) }}</th><th class="num">Perubahan</th>@endif</tr></thead>
    <tbody>
      {!! $section('ASET', $sec['assets'], 'TOTAL ASET', $data['asset_total'], $prev['asset_total'] ?? 0) !!}
      {!! $section('LIABILITAS', $sec['liabilities'], 'TOTAL LIABILITAS', $data['liability_total'], $prev['liability_total'] ?? 0) !!}
      {!! $section('EKUITAS', $sec['equity'], 'TOTAL EKUITAS', $data['equity_total'], $prev['equity_total'] ?? 0,
            ['Laba (rugi) berjalan', $data['profit'], $prev['profit'] ?? 0]) !!}
      <tr class="table-success"><th class="text-end">TOTAL LIABILITAS + EKUITAS</th><th class="num">{{ $fm($data['liab_equity_total']) }}</th>
        @if($compare)<th class="num">{{ $fm($prev['liab_equity_total']) }}</th><th class="num">{!! $delta($data['liab_equity_total'], $prev['liab_equity_total']) !!}</th>@endif</tr>
    </tbody>
  </table>
  </div>

  <div class="alert alert-light border mt-3 mb-0">
    <b>Validasi keseimbangan:</b> Aset = Liabilitas + Ekuitas.
    Selisih Rp {{ $f(abs($data['asset_total'] - $data['liab_equity_total'])) }}
    @if($compare && !$prev['balanced'])<div class="text-danger small">Posisi {{ $d($prevDate) }} tidak seimbang.</div>@endif
  </div>

  {{-- Tanda tangan --}}
  <div class="row g-3 mt-4 text-center">
    <div class="col-6"><div class="small text-muted">Disiapkan oleh</div>
      <div style="height:70px"></div>
      <div class="fw-semibold border-top pt-1 mx-4">{{ $S['sign_prepared_name'] ?: '........................' }}</div>
      <div class="small text-muted">{{ $S['sign_prepared_title'] }}</div></div>
    <div class="col-6"><div class="small text-muted">Disetujui oleh</div>
      <div style="height:70px"></div>
      <div class="fw-semibold border-top pt-1 mx-4">{{ $S['sign_approved_name'] ?: '........................' }}</div>
      <div class="small text-muted">{{ $S['sign_approved_title'] }}</div></div>
  </div>
  <div class="small text-muted text-center mt-4">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }}</div>
</div>
<p class="small text-muted text-center mt-2 no-print">Pembanding adalah akhir bulan sebelum tanggal laporan. Prive tampil negatif karena mengurangi ekuitas.
  Laba berjalan adalah laba/rugi kumulatif sejak awal usaha.</p>
@endsection