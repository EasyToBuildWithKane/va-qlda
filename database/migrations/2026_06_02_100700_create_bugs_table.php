<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bugs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique(); // BUG-0001, set after create
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('steps_to_reproduce')->nullable();
            $table->text('expected')->nullable();
            $table->text('actual')->nullable();
            $table->string('environment')->nullable();
            $table->string('severity')->default('major');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            // Reporter: internal employee OR external (name/email)
            $table->foreignId('reporter_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            // "Người sửa"
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index('status');
            $table->index('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bugs');
    }
};
