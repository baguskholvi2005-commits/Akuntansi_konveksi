<?php

namespace App\Support;

class Menu
{
    private const ALL = ['*'];
    private const FIN = ['admin', 'keuangan'];
    private const OPS = ['admin', 'keuangan', 'gudang'];
    private const VIEW = ['admin', 'keuangan', 'pemilik'];

    private static function all(): array
    {
        return [
            ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'route' => 'dashboard', 'active' => ['dashboard'], 'roles' => self::ALL],

            ['label' => 'Transaksi', 'items' => [
                ['label' => 'Pembelian',            'icon' => 'bi-cart3',              'route' => 'purchases.index',      'active' => ['purchases.*'],      'roles' => self::OPS],
                ['label' => 'Produksi',             'icon' => 'bi-gear-wide-connected','route' => 'productions.index',    'active' => ['productions.*'],    'roles' => self::OPS],
                ['label' => 'Pesanan dan Penjualan','icon' => 'bi-bag-check',          'route' => 'orders.index',         'active' => ['orders.*'],         'roles' => self::OPS],
                ['label' => 'Retur Penjualan',      'icon' => 'bi-arrow-return-left',  'route' => 'sales-returns.index',  'active' => ['sales-returns.*'],  'roles' => self::FIN],
                ['label' => 'Pengeluaran',          'icon' => 'bi-wallet2',            'route' => 'expenses.index',       'active' => ['expenses.*'],       'roles' => self::FIN],
                ['label' => 'Modal dan Prive',      'icon' => 'bi-piggy-bank',         'route' => 'equity.index',         'active' => ['equity.*'],         'roles' => self::FIN],
            ]],

            ['label' => 'Persediaan', 'items' => [
                ['label' => 'Laporan Persediaan',   'icon' => 'bi-boxes',              'route' => 'inventory.report',     'active' => ['inventory.report'], 'roles' => self::ALL],
                ['label' => 'Kartu Persediaan',     'icon' => 'bi-card-list',          'route' => 'inventory.card',       'active' => ['inventory.card'],   'roles' => self::ALL],
                ['label' => 'Penyesuaian Stok',     'icon' => 'bi-sliders',            'route' => 'stock-adjustments.index','active' => ['stock-adjustments.*'], 'roles' => self::OPS],
            ]],

            ['label' => 'Akuntansi', 'items' => [
                ['label' => 'Jurnal Umum',          'icon' => 'bi-journal-text',       'route' => 'journals.index',       'active' => ['journals.*'],       'roles' => self::VIEW],
                ['label' => 'Buku Besar',           'icon' => 'bi-book',               'route' => 'ledger.index',         'active' => ['ledger.*'],         'roles' => self::VIEW],
                ['label' => 'Neraca Saldo',         'icon' => 'bi-calculator',         'route' => 'trial.index',          'active' => ['trial.*'],          'roles' => self::VIEW],
            ]],

            ['label' => 'Laporan', 'items' => [
                ['label' => 'Laba Rugi',            'icon' => 'bi-graph-up-arrow',     'route' => 'reports.income',       'active' => ['reports.income'],   'roles' => self::VIEW],
                ['label' => 'Neraca',               'icon' => 'bi-bank',               'route' => 'reports.balance',      'active' => ['reports.balance'],  'roles' => self::VIEW],
                ['label' => 'Arus Kas',             'icon' => 'bi-cash-stack',         'route' => 'reports.cashflow',     'active' => ['reports.cashflow'], 'roles' => self::VIEW],
                ['label' => 'Perubahan Modal',      'icon' => 'bi-pie-chart',          'route' => 'reports.equity',       'active' => ['reports.equity'],   'roles' => self::VIEW],
                ['label' => 'Laporan Pembelian',    'icon' => 'bi-cart-check',         'route' => 'reports.purchases',    'active' => ['reports.purchases'],'roles' => self::VIEW],
                ['label' => 'Laporan Penjualan',    'icon' => 'bi-receipt-cutoff',     'route' => 'reports.sales',        'active' => ['reports.sales'],    'roles' => self::VIEW],
                ['label' => 'Utang dan Piutang',    'icon' => 'bi-hourglass-split',    'route' => 'reports.debts',        'active' => ['reports.debts'],    'roles' => self::VIEW],
                ['label' => 'Biaya Produksi',       'icon' => 'bi-gear-wide-connected','route' => 'reports.production',   'active' => ['reports.production'],'roles' => self::VIEW],
            ]],

            ['label' => 'Data Master', 'items' => [
                ['label' => 'Data Master',          'icon' => 'bi-box-seam',           'route' => 'items.index',          'active' => ['items.*','suppliers.*','customers.*','recipes.*'], 'roles' => ['admin']],
            ]],

            ['label' => 'Pengaturan', 'items' => [
                ['label' => 'Profil Perusahaan',    'icon' => 'bi-building',           'route' => 'settings.company',     'active' => ['settings.company*'],   'roles' => ['admin']],
                ['label' => 'Tampilan',             'icon' => 'bi-palette',            'route' => 'settings.appearance',  'active' => ['settings.appearance*'],'roles' => ['admin']],
                ['label' => 'Pengguna',             'icon' => 'bi-person-gear',        'route' => 'settings.users',       'active' => ['settings.users*'],     'roles' => ['admin']],
            ]],
        ];
    }

    private static function allowed(array $roles, string $role): bool
    {
        return $roles === ['*'] || in_array($role, $roles, true);
    }

    /** Menu yang boleh dilihat peran ini. Grup yang kosong disembunyikan. */
    public static function forRole(string $role): array
    {
        $out = [];

        foreach (self::all() as $g) {
            if (isset($g['items'])) {
                $items = [];
                foreach ($g['items'] as $it) {
                    if (!self::allowed($it['roles'], $role)) {
                        continue;
                    }
                    $it['is_active'] = request()->routeIs(...$it['active']);
                    $items[] = $it;
                }
                if (!$items) {
                    continue;
                }
                $out[] = [
                    'label' => $g['label'],
                    'items' => $items,
                    'open'  => collect($items)->contains('is_active', true),
                ];
            } else {
                if (!self::allowed($g['roles'], $role)) {
                    continue;
                }
                $g['is_active'] = request()->routeIs(...$g['active']);
                $out[] = $g;
            }
        }

        return $out;
    }

    /** [grup, menu] untuk remah roti di atas halaman. */
    public static function crumb(array $menu): array
    {
        foreach ($menu as $g) {
            if (isset($g['items'])) {
                foreach ($g['items'] as $it) {
                    if ($it['is_active']) {
                        return [$g['label'], $it['label']];
                    }
                }
            } elseif ($g['is_active']) {
                return [null, $g['label']];
            }
        }

        return [null, null];
    }
}