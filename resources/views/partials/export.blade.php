@php
  $map = [
    'reports.income'   => 'income',
    'reports.balance'  => 'balance',
    'reports.cashflow' => 'cashflow',
    'reports.equity'   => 'equity',
    'reports.purchases'=> 'purchases',
    'reports.sales'    => 'sales',
    'reports.debts'    => 'debts',
    'trial.index'      => 'trial',
    'ledger.index'     => 'ledger',
    'inventory.card'   => 'inventory-card',
    'inventory.report' => 'inventory-report',
  ];
  $key = $map[request()->route()?->getName()] ?? null;
  $q   = request()->query();
@endphp
@if($key)
<div class="btn-group no-print" role="group">
  <a class="btn btn-outline-danger" href="{{ route('export', ['report' => $key, 'format' => 'pdf'] + $q) }}">PDF</a>
  <a class="btn btn-outline-success" href="{{ route('export', ['report' => $key, 'format' => 'xlsx'] + $q) }}">Excel</a>
</div>
@endif