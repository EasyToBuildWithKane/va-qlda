<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hours logged against a task. `rate_snapshot` + `cost` are frozen at log time
 * so historical labour cost is unaffected by later rate changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->string('note')->nullable();
            $table->decimal('rate_snapshot', 15, 2)->nullable(); // effective hourly rate at log time
            $table->decimal('cost', 15, 2)->nullable();          // hours * rate_snapshot
            $table->timestamps();

            $table->index(['task_id', 'date']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worklogs');
    }
};
