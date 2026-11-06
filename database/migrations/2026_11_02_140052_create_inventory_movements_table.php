<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->date('date');
            $table->string('reference');
            $table->string('description')->nullable();
            $table->nullableMorphs('source');
            $table->decimal('in_qty', 15, 2)->default(0);
            $table->decimal('in_cost', 15, 4)->default(0);
            $table->decimal('in_value', 15, 2)->default(0);
            $table->decimal('out_qty', 15, 2)->default(0);
            $table->decimal('out_cost', 15, 4)->default(0);
            $table->decimal('out_value', 15, 2)->default(0);
            $table->decimal('balance_qty', 15, 2);
            $table->decimal('balance_cost', 15, 4);
            $table->decimal('balance_value', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
