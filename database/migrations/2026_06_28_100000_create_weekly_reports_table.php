<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained('sprints')->nullOnDelete();
            $table->unsignedSmallInteger('week_number')->default(1);
            $table->date('week_start');
            $table->date('week_end');
            $table->string('title')->nullable();

            $table->string('status', 16)->default('draft');
            $table->text('executive_summary')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('kpi_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->string('data_hash')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->text('reject_reason')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'sprint_id', 'week_number'], 'wr_proj_sprint_week_uq');
            $table->index(['project_id', 'status'], 'wr_proj_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
