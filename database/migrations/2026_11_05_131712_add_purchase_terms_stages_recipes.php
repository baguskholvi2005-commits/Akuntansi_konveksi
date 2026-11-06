<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->date('due_date')->nullable()->after('date');
            $t->decimal('discount', 15, 2)->default(0)->after('payment_method');
            $t->decimal('shipping_cost', 15, 2)->default(0)->after('discount');
        });

        Schema::table('purchase_items', function (Blueprint $t) {
            $t->decimal('discount_pct', 5, 2)->default(0)->after('unit_price');
        });

        Schema::table('productions', function (Blueprint $t) {
            $t->string('stage', 20)->default('potong')->after('status');
        });

        Schema::create('recipes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('items')->cascadeOnDelete();
            $t->foreignId('material_id')->constrained('items');
            $t->decimal('qty_per_unit', 15, 4);
            $t->decimal('waste_pct', 5, 2)->default(0);
            $t->timestamps();
            $t->unique(['product_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
        Schema::table('productions', fn(Blueprint $t) => $t->dropColumn('stage'));
        Schema::table('purchase_items', fn(Blueprint $t) => $t->dropColumn('discount_pct'));
        Schema::table('purchases', fn(Blueprint $t) => $t->dropColumn(['due_date', 'discount', 'shipping_cost']));
    }
};