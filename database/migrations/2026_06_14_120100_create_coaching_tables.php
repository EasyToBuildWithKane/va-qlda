<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->foreignId('student_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('planning');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_fee', 15, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('total_hours', 6, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('coach_id');
            $table->index('student_id');
        });

        Schema::create('coaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('coaching_courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('session_number');
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_hours', 4, 2)->nullable();
            $table->string('topic', 500)->nullable();
            $table->longText('content')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['course_id', 'session_number'], 'coach_sess_course_num_uq');
            $table->index(['course_id', 'status']);
        });

        Schema::create('coaching_session_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('coaching_sessions')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->string('url', 1000)->nullable();
            $table->string('path', 1000)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('coaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('coaching_sessions')->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('todo');
            $table->string('submission_path', 1000)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('coaching_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('coaching_courses')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('coaching_sessions')->cascadeOnDelete();
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->boolean('is_viewed')->default(false);
            $table->boolean('is_in_progress')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['course_id', 'session_id', 'system_account_id'], 'coach_prog_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_progress');
        Schema::dropIfExists('coaching_assignments');
        Schema::dropIfExists('coaching_session_materials');
        Schema::dropIfExists('coaching_sessions');
        Schema::dropIfExists('coaching_courses');
    }
};
