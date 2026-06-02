<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // public/API id (D1)

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->json('projects')->nullable();

            $table->date('date');
            $table->string('title');
            $table->text('goals_today')->nullable();
            $table->text('progress_update')->nullable();
            $table->text('results_impact')->nullable();
            $table->text('blockers')->nullable();
            $table->text('improvement_suggestions')->nullable();
            $table->text('highlights')->nullable();
            $table->text('plan_tomorrow')->nullable();

            $table->string('status')->default('draft');     // ReportStatus enum
            $table->boolean('is_late')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();        // reviewer feedback on reject

            $table->timestamps();

            $table->unique(['employee_id', 'date']);         // 1 report / person / day
            $table->index('status');
            $table->index('date');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
