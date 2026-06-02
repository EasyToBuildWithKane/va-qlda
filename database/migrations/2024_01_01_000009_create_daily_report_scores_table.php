<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_report_scores')) {
            return;
        }

        Schema::create('daily_report_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id')->unique();
            $table->decimal('task_completion', 4, 2);
            $table->decimal('skill_score', 4, 2);
            $table->decimal('attitude_score', 4, 2);
            $table->decimal('kaizen_score', 4, 2);
            $table->decimal('expertise_score', 4, 2);
            $table->decimal('total_score', 4, 2);
            $table->string('grade', 1);
            $table->unsignedBigInteger('reviewer_id');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('report_id', 'daily_rpt_scores_report_fk')
                ->references('id')->on('daily_reports')->cascadeOnDelete();
            $table->foreign('reviewer_id', 'daily_rpt_scores_reviewer_fk')
                ->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_scores');
    }
};
