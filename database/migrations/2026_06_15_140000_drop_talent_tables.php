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
        // Intentionally irreversible: the Talent Management module was permanently
        // removed and its create migration no longer exists, so there is no schema
        // to restore. Rolling back is a no-op rather than a failure.
    }
};
