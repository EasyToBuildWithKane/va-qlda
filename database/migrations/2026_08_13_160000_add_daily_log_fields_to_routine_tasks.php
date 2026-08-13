<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_tasks', function (Blueprint $table) {
            $table->date('work_date')->nullable()->after('position');
            $table->timestamp('started_at')->nullable()->after('work_date');
            $table->timestamp('ended_at')->nullable()->after('started_at');
            $table->decimal('estimate_hours', 6, 2)->nullable()->after('ended_at');
            $table->decimal('actual_hours', 6, 2)->nullable()->after('estimate_hours');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('actual_hours');
            $table->text('blockers')->nullable()->after('progress_percent');
            $table->text('risks')->nullable()->after('blockers');

            $table->index(['employee_id', 'work_date'], 'routine_tasks_emp_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('routine_tasks', function (Blueprint $table) {
            $table->dropIndex('routine_tasks_emp_date_idx');
            $table->dropColumn([
                'work_date',
                'started_at',
                'ended_at',
                'estimate_hours',
                'actual_hours',
                'progress_percent',
                'blockers',
                'risks',
            ]);
        });
    }
};
