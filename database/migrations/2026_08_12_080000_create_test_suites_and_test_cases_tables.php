<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_suites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('project_id');
        });

        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suite_id')->nullable()->constrained('test_suites')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('blocker_id')->nullable()->constrained('blockers')->nullOnDelete();
            $table->string('code', 32)->nullable();
            $table->string('title');
            $table->text('preconditions')->nullable();
            $table->json('steps')->nullable();
            $table->text('expected_result')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('draft');
            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('last_result')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('last_run_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('last_actual_result')->nullable();
            $table->text('last_run_note')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status'], 'tc_proj_status_idx');
            $table->index(['project_id', 'code'], 'tc_proj_code_idx');
            $table->index('suite_id');
            $table->index('task_id');
            $table->index('owner_id');
        });

        Schema::create('test_case_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_case_id')->constrained('test_cases')->cascadeOnDelete();
            $table->string('result');
            $table->text('actual_result')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('executed_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('executed_at');
            $table->foreignId('blocker_id')->nullable()->constrained('blockers')->nullOnDelete();
            $table->timestamps();

            $table->index(['test_case_id', 'executed_at'], 'tcr_case_exec_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_case_runs');
        Schema::dropIfExists('test_cases');
        Schema::dropIfExists('test_suites');
    }
};
