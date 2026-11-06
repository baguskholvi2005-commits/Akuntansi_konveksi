<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('date');
            $t->date('finished_date')->nullable();
            $t->foreignId('item_id')->constrained();
            $t->decimal('qty', 15, 2);
            $t->decimal('material_cost', 15, 2)->default(0);
            $t->decimal('labor_cost', 15, 2)->default(0);
            $t->decimal('overhead_cost', 15, 2)->default(0);
            $t->decimal('total_cost', 15, 2)->default(0);
            $t->decimal('unit_cost', 15, 4)->default(0);
            $t->enum('status', ['draft', 'proses', 'selesai'])->default('draft');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('production_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->decimal('qty', 15, 2);
            $t->decimal('unit_cost', 15, 4)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_materials');
        Schema::dropIfExists('productions');
    }
};