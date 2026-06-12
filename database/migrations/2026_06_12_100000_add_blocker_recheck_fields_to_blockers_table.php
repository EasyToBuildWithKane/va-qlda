<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blockers', function (Blueprint $table) {
            $table->string('recheck_result', 32)->nullable()->after('resolved_at');
            $table->text('recheck_note')->nullable()->after('recheck_result');
            $table->timestamp('rechecked_at')->nullable()->after('recheck_note');
            $table->foreignId('rechecked_by_id')->nullable()->after('rechecked_at')->constrained('employees')->nullOnDelete();

            $table->index(['status', 'recheck_result'], 'blockers_status_recheck_idx');
        });

        DB::table('blockers')
            ->where('status', 'resolved')
            ->whereNull('recheck_result')
            ->update(['recheck_result' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('blockers', function (Blueprint $table) {
            $table->dropIndex('blockers_status_recheck_idx');
            $table->dropConstrainedForeignId('rechecked_by_id');
            $table->dropColumn(['recheck_result', 'recheck_note', 'rechecked_at']);
        });
    }
};
