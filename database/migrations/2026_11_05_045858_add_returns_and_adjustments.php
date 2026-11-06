<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bagian piutang pesanan yang dipotong oleh retur
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->decimal('receivable_cut', 15, 2)->default(0)->after('paid_amount');
        });

        Schema::create('sales_returns', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('date');
            $t->foreignId('sales_order_id')->constrained();
            $t->decimal('total', 15, 2);                         // nilai retur (harga jual)
            $t->decimal('receivable_cut', 15, 2)->default(0);    // memotong piutang
            $t->decimal('refund_amount', 15, 2)->default(0);     // uang dikembalikan
            $t->string('refund_account_code', 10)->nullable();   // 1101 / 1102
            $t->decimal('cogs', 15, 2)->default(0);              // HPP yang dikembalikan
            $t->string('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('sales_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->decimal('qty', 15, 2);
            $t->decimal('unit_price', 15, 2);
            $t->decimal('subtotal', 15, 2);
            $t->decimal('unit_cost', 15, 4)->default(0);
            $t->decimal('cost_value', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('date');
            $t->foreignId('item_id')->constrained();
            $t->decimal('counted_qty', 15, 2);                   // hasil hitung fisik
            $t->decimal('system_qty', 15, 2)->nullable();        // stok sistem saat disetujui
            $t->decimal('diff_qty', 15, 2)->nullable();          // fisik - sistem (bisa negatif)
            $t->decimal('unit_cost', 15, 4)->nullable();
            $t->decimal('value', 15, 2)->default(0);
            $t->string('reason');
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->dropColumn('receivable_cut');
        });
    }
};