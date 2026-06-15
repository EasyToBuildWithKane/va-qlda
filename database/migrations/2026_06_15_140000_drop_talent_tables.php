<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes Talent Management tables (KPI, 360°, succession, career ladder, etc.).
 * Profile skills live on employees.skills + employees.meta.skill_details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('career_levels');
        Schema::dropIfExists('succession_plans');
        Schema::dropIfExists('feedback_reviews');
        Schema::dropIfExists('learning_items');
        Schema::dropIfExists('performance_kpis');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('employee_skills');
    }

    public function down(): void
    {
        // Recreate via 2026_06_15_120000_create_talent_tables if rollback needed.
    }
};
