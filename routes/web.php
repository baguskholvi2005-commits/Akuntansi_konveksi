<?php

use App\Http\Controllers\{
    SupplierController, CustomerController, ItemController,
    PurchaseController, ProductionController, SalesOrderController,
    ExpenseController, OwnerTransactionController,
    JournalController, LedgerController, TrialBalanceController,
    InventoryReportController, FinancialReportController, TradeReportController,
    DashboardController, ExportController,
    SalesReturnController, StockAdjustmentController,
    SettingController, UserController, AccountController, RecipeController,ProductionCostController
};
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Ganti kata sandi sendiri: semua peran
    Route::get('account/password', [AccountController::class, 'password'])->name('account.password');
    Route::put('account/password', [AccountController::class, 'update'])->name('account.password.update');

    // ===== Admin saja: data master dan pengaturan =====
    Route::middleware('role:admin')->group(function () {
        Route::resource('suppliers', SupplierController::class)->except('show');
        Route::resource('customers', CustomerController::class)->except('show');
        Route::resource('items', ItemController::class)->except('show');

        Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::get('recipes/{item}', [RecipeController::class, 'edit'])->name('recipes.edit');
        Route::put('recipes/{item}', [RecipeController::class, 'update'])->name('recipes.update');

        Route::get('settings', fn() => redirect()->route('settings.company'))->name('settings.index');
        Route::get('settings/company', [SettingController::class, 'company'])->name('settings.company');
        Route::post('settings/company', [SettingController::class, 'saveCompany'])->name('settings.company.save');
        Route::get('settings/appearance', [SettingController::class, 'appearance'])->name('settings.appearance');
        Route::post('settings/appearance', [SettingController::class, 'saveAppearance'])->name('settings.appearance.save');
        Route::post('settings/appearance/reset', [SettingController::class, 'resetAppearance'])->name('settings.appearance.reset');

        Route::get('settings/users', [UserController::class, 'index'])->name('settings.users');
        Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
        Route::put('settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
        Route::post('settings/users/{user}/toggle', [UserController::class, 'toggle'])->name('settings.users.toggle');
    });

    // ===== Admin dan keuangan (HARUS di atas grup 3 peran) =====
    Route::middleware('role:admin,keuangan')->group(function () {
        Route::resource('sales-returns', SalesReturnController::class)
            ->parameters(['sales-returns' => 'salesReturn'])
            ->only(['index', 'create', 'store', 'show']);
        Route::post('stock-adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])
            ->name('stock-adjustments.approve');

        Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);
        Route::post('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');

        Route::resource('equity', OwnerTransactionController::class)->only(['index', 'create', 'store']);
        Route::post('equity/{tx}/void', [OwnerTransactionController::class, 'void'])->name('equity.void');

        Route::resource('journals', JournalController::class)->only(['create', 'store']);
        Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse');

        Route::resource('orders', SalesOrderController::class)->only(['create', 'store', 'destroy']);
        Route::post('orders/{order}/pay', [SalesOrderController::class, 'pay'])->name('orders.pay');
        Route::post('orders/{order}/deliver', [SalesOrderController::class, 'deliver'])->name('orders.deliver');

        Route::post('purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm');
        Route::post('purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
        Route::post('productions/{production}/finish', [ProductionController::class, 'finish'])->name('productions.finish');
        Route::post('productions/{production}/pay', [ProductionController::class, 'pay'])->name('productions.pay');
    });

    // ===== Admin, keuangan, gudang =====
    Route::middleware('role:admin,keuangan,gudang')->group(function () {
        Route::resource('stock-adjustments', StockAdjustmentController::class)
            ->parameters(['stock-adjustments' => 'adjustment'])
            ->only(['index', 'create', 'store', 'show', 'destroy']);

        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

        Route::resource('productions', ProductionController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::post('productions/{production}/start', [ProductionController::class, 'start'])->name('productions.start');

        Route::post('productions/{production}/stage', [ProductionController::class, 'stage'])->name('productions.stage');
        Route::get('productions/{production}/ticket', [ProductionController::class, 'ticket'])->name('productions.ticket');

        Route::resource('orders', SalesOrderController::class)->only(['index', 'show']);
        Route::post('orders/{order}/progress', [SalesOrderController::class, 'progress'])->name('orders.progress');
    });

    // ===== Admin, keuangan, pemilik: laporan dan jurnal (lihat) =====
    Route::middleware('role:admin,keuangan,pemilik')->group(function () {
        Route::get('reports/income', [FinancialReportController::class, 'income'])->name('reports.income');
        Route::get('reports/balance', [FinancialReportController::class, 'balance'])->name('reports.balance');
        Route::get('reports/cashflow', [FinancialReportController::class, 'cashflow'])->name('reports.cashflow');
        Route::get('reports/equity', [FinancialReportController::class, 'equity'])->name('reports.equity');
        Route::get('reports/purchases', [TradeReportController::class, 'purchases'])->name('reports.purchases');
        Route::get('reports/sales', [TradeReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/debts', [TradeReportController::class, 'debts'])->name('reports.debts');
        Route::get('reports/production', [ProductionCostController::class, 'index'])->name('reports.production');

        Route::resource('journals', JournalController::class)->only(['index', 'show']);
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('trial-balance', [TrialBalanceController::class, 'index'])->name('trial.index');
    });

    // ===== Semua peran: persediaan dan ekspor =====
    Route::middleware('role:admin,keuangan,gudang,pemilik')->group(function () {
        Route::get('export/{report}/{format}', [ExportController::class, 'show'])
            ->whereIn('report', [
                'income', 'balance', 'cashflow', 'equity', 'trial', 'ledger',
                'inventory-card', 'inventory-report', 'purchases', 'sales', 'debts',
            ])
            ->whereIn('format', ['pdf', 'xlsx'])
            ->name('export');

        Route::get('inventory-card', [InventoryReportController::class, 'card'])->name('inventory.card');
        Route::get('inventory-report', [InventoryReportController::class, 'report'])->name('inventory.report');
    });
});

require __DIR__ . '/auth.php';