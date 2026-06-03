<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('root_cause')->nullable();
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->foreignId('raised_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('raised_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'code']);
            $table->index('status');
            $table->index('task_id');
            $table->index('severity');
            $table->index('owner_id');
        });

        Schema::create('blocker_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('blockers')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_image')->default(false);
            $table->timestamps();

            $table->index('blocker_id');
        });

        Schema::create('blocker_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('blockers')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event', 40);
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['blocker_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocker_activities');
        Schema::dropIfExists('blocker_attachments');
        Schema::dropIfExists('blockers');
    }
};
