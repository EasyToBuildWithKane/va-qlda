<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique(); // FB-0001, set after create
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('improvement');
            $table->string('title');
            $table->text('description');
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->string('priority')->default('medium');
            $table->string('status')->default('new');
            // Reporter: internal employee OR external (name/email)
            $table->foreignId('reporter_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            // "Người sửa feedback"
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('status');
            $table->index('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
