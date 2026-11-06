<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('date');
            $t->string('category', 20);                      // gaji, listrik, air, dst.
            $t->string('account_code', 10);                  // akun beban (6101 dst.)
            $t->string('description');
            $t->decimal('amount', 15, 2);
            $t->string('payment_account_code', 10);          // 1101 Kas / 1102 Bank
            $t->string('proof_path')->nullable();            // file bukti transaksi
            $t->enum('status', ['posted', 'void'])->default('posted');
            $t->string('void_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('owner_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('date');
            $t->enum('type', ['modal', 'prive']);
            $t->decimal('amount', 15, 2);
            $t->string('account_code', 10);                  // 1101 Kas / 1102 Bank
            $t->string('notes')->nullable();
            $t->enum('status', ['posted', 'void'])->default('posted');
            $t->string('void_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_transactions');
        Schema::dropIfExists('expenses');
    }
};