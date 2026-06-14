<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talent Management module — the data behind the deep member profile:
 * skill matrix (with levels), certifications, KPIs, learning, 360° feedback,
 * succession planning, and the global career ladder used for skill-gap analysis.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rich, leveled skill matrix (the JSON employees.skills stays as a quick list).
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40)->nullable();   // backend|frontend|ai|...
            $table->unsignedTinyInteger('level')->default(1); // 1..5
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->boolean('is_certified')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('employee_id');
            $table->unique(['employee_id', 'name'], 'emp_skill_unique');
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('credential_url', 1000)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        Schema::create('performance_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('period_type', 20);             // month|quarter|year
            $table->string('period', 20);                  // 2026-06 | 2026-Q2 | 2026
            $table->string('name');
            $table->decimal('target', 12, 2)->nullable();
            $table->decimal('actual', 12, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->timestamps();

            $table->index(['employee_id', 'period_type', 'period'], 'kpi_emp_period_idx');
        });

        Schema::create('learning_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('status', 20)->default('planned'); // completed|in_progress|recommended|planned
            $table->unsignedTinyInteger('progress')->default(0); // 0..100
            $table->string('url', 1000)->nullable();
            $table->date('started_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status'], 'learn_emp_status_idx');
        });

        // 360° feedback — subject = employee_id, optional reviewer = reviewer_id.
        Schema::create('feedback_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('review_type', 20);             // self|manager|peer
            $table->string('period', 20)->nullable();
            $table->unsignedTinyInteger('rating_technical')->nullable();      // 1..5
            $table->unsignedTinyInteger('rating_communication')->nullable();
            $table->unsignedTinyInteger('rating_ownership')->nullable();
            $table->unsignedTinyInteger('rating_leadership')->nullable();
            $table->unsignedTinyInteger('rating_teamwork')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'review_type'], 'review_emp_type_idx');
        });

        Schema::create('succession_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('readiness', 20)->default('not_ready'); // not_ready|potential|ready
            $table->unsignedTinyInteger('risk_score')->nullable();       // 0..100 (flight risk)
            $table->unsignedTinyInteger('retention_score')->nullable();  // 0..100
            $table->unsignedTinyInteger('promotion_score')->nullable();  // 0..100
            $table->string('target_role')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Global career ladder (junior → manager) with promotion requirements.
        Schema::create('career_levels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();           // intern|junior|middle|senior|lead|manager
            $table->string('name');
            $table->unsignedTinyInteger('rank');
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();      // {skills:{Laravel:4,...}, kpi:80, certifications:1}
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_levels');
        Schema::dropIfExists('succession_plans');
        Schema::dropIfExists('feedback_reviews');
        Schema::dropIfExists('learning_items');
        Schema::dropIfExists('performance_kpis');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('employee_skills');
    }
};
