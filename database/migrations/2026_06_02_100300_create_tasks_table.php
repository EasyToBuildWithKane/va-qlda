<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained('sprints')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('epic_id')->nullable()->constrained('epics')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->string('phase')->nullable();
            $table->boolean('is_milestone')->default(false);
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->timestamp('work_started_at')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimate_hours', 8, 2)->nullable();
            $table->decimal('story_points', 6, 2)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'sprint_id']);
            $table->index(['project_id', 'phase']);
            $table->index(['project_id', 'is_milestone']);
            $table->index(['assignee_id', 'status']);
            $table->index('sprint_id');
            $table->index('assignee_id');
            $table->index('reporter_id');
            $table->index('reviewer_id');
            $table->index('parent_id');
            $table->index('epic_id');
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->primary(['task_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['task_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_image')->default(false);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event', 40);
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
