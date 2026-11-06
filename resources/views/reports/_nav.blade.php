@php
  $tabs = [
    'reports.income'    => 'Laba Rugi',
    'reports.balance'   => 'Neraca',
    'reports.cashflow'  => 'Arus Kas',
    'reports.equity'    => 'Perubahan Modal',
    'reports.purchases' => 'Pembelian',
    'reports.sales'     => 'Penjualan',
    'reports.debts'     => 'Utang dan Piutang',
    'reports.production' => 'Biaya Produksi',
  ];
@endphp
<ul class="nav nav-tabs mb-3 no-print">
  @foreach($tabs as $route => $label)
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a>
    </li>
  @endforeach
</ul>