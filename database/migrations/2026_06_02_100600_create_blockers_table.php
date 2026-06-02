<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khúc mắc / vướng mắc — impediments raised against a project or a task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->foreignId('raised_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('raised_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockers');
    }
};
