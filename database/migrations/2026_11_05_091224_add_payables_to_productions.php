<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->decimal('labor_payable', 15, 2)->default(0)->after('overhead_cost');
            $t->decimal('overhead_payable', 15, 2)->default(0)->after('labor_payable');
        });

        Schema::create('production_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->enum('kind', ['labor', 'overhead']);
            $t->decimal('amount', 15, 2);
            $t->string('account_code', 10);               // 1101 Kas / 1102 Bank
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        // Isi otomatis untuk produksi lama: baca jurnal tenaga kerja dan overhead
        // yang kreditnya ke Utang Gaji (2103) atau Utang Usaha (2101).
        $acc = DB::table('accounts')->pluck('id', 'code');

        foreach (DB::table('productions')->where('status', 'selesai')->get() as $p) {
            $vals = [];
            foreach (['labor_payable' => ['labor', '2103'], 'overhead_payable' => ['overhead', '2101']] as $col => [$event, $code]) {
                if (!isset($acc[$code])) {
                    continue;
                }
                $vals[$col] = (float) DB::table('journals as j')
                    ->join('journal_lines as jl', 'jl.journal_id', '=', 'j.id')
                    ->where('j.source_type', 'App\Models\Production')
                    ->where('j.source_id', $p->id)
                    ->where('j.event', $event)
                    ->where('jl.account_id', $acc[$code])
                    ->sum('jl.credit');
            }
            if ($vals) {
                DB::table('productions')->where('id', $p->id)->update($vals);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_payments');
        Schema::table('productions', function (Blueprint $t) {
            $t->dropColumn(['labor_payable', 'overhead_payable']);
        });
    }
};