<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm điểm phiếu đánh giá: submission theo (phiếu × nhân sự × vai trò hội đồng).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_form_submissions')) {
            Schema::create('evaluation_form_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('evaluation_forms')->cascadeOnDelete();
                $table->unsignedBigInteger('assignee_id');
                $table->string('rater_role_key', 64);
                $table->unsignedBigInteger('rater_employee_id');
                $table->string('status', 16)->default('draft');
                $table->decimal('total_score', 10, 2)->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->timestamps();

                $table->foreign('assignee_id', 'eval_form_sub_asg_fk')
                    ->references('id')->on('evaluation_form_assignees')->cascadeOnDelete();
                $table->foreign('rater_employee_id', 'eval_form_sub_rater_fk')
                    ->references('id')->on('employees')->restrictOnDelete();

                $table->unique(['form_id', 'assignee_id', 'rater_role_key'], 'eval_form_sub_uq');
                $table->index(['form_id', 'status'], 'eval_form_sub_status_idx');
            });
        }

        if (! Schema::hasTable('evaluation_form_score_lines')) {
            Schema::create('evaluation_form_score_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('submission_id');
                $table->unsignedBigInteger('form_criterion_id');
                $table->string('score_level_code', 64)->nullable();
                $table->string('score_level_label', 255)->nullable();
                $table->decimal('score_weight', 8, 2)->default(0);
                $table->timestamps();

                $table->foreign('submission_id', 'eval_form_line_sub_fk')
                    ->references('id')->on('evaluation_form_submissions')->cascadeOnDelete();
                $table->foreign('form_criterion_id', 'eval_form_line_crit_fk')
                    ->references('id')->on('evaluation_form_criteria')->cascadeOnDelete();

                $table->unique(['submission_id', 'form_criterion_id'], 'eval_form_line_uq');
            });
        }

        if (! Schema::hasTable('evaluation_form_field_values')) {
            Schema::create('evaluation_form_field_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('submission_id');
                $table->unsignedBigInteger('form_field_id');
                $table->text('value')->nullable();
                $table->timestamps();

                $table->foreign('submission_id', 'eval_form_fval_sub_fk')
                    ->references('id')->on('evaluation_form_submissions')->cascadeOnDelete();
                $table->foreign('form_field_id', 'eval_form_fval_field_fk')
                    ->references('id')->on('evaluation_form_fields')->cascadeOnDelete();

                $table->unique(['submission_id', 'form_field_id'], 'eval_form_fval_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_form_field_values');
        Schema::dropIfExists('evaluation_form_score_lines');
        Schema::dropIfExists('evaluation_form_submissions');
    }
};
