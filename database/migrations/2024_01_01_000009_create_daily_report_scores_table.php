<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->unique()        // 1-1 with a report
                ->constrained('daily_reports')->cascadeOnDelete();

            $table->decimal('task_completion', 4, 2);
            $table->decimal('skill_score', 4, 2);
            $table->decimal('attitude_score', 4, 2);
            $table->decimal('kaizen_score', 4, 2);
            $table->decimal('expertise_score', 4, 2);

            $table->decimal('total_score', 4, 2);            // computed at score time
            $table->string('grade', 1);                      // Grade enum (S..D)

            $table->foreignId('reviewer_id')->constrained('employees');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_scores');
    }
};
