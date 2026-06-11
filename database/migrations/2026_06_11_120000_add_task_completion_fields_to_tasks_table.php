<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('actual_hours', 8, 2)->nullable()->after('estimate_hours');
            $table->text('completion_note')->nullable()->after('actual_hours');
            $table->timestamp('completed_at')->nullable()->after('completion_note');
            $table->string('hours_timing', 20)->nullable()->after('completed_at');
            $table->string('sla_result', 20)->nullable()->after('hours_timing');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'actual_hours',
                'completion_note',
                'completed_at',
                'hours_timing',
                'sla_result',
            ]);
        });
    }
};
