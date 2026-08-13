<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index(['employee_id', 'status'], 'routine_tasks_emp_status_idx');
            $table->index(['employee_id', 'position'], 'routine_tasks_emp_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_tasks');
    }
};
