<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu đánh giá (instances): loại ĐG, header, hội đồng, trường tùy biến, tiêu chí snapshot, nhân sự.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_form_types')) {
            Schema::create('evaluation_form_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->timestamps();

                $table->unique('name', 'eval_form_type_name_uq');
                $table->index(['is_active', 'sort_order'], 'eval_form_type_active_idx');
            });
        }

        if (! Schema::hasTable('evaluation_forms')) {
            Schema::create('evaluation_forms', function (Blueprint $table) {
                $table->id();
                $table->string('form_code', 100);
                $table->string('name');
                $table->foreignId('template_id')->nullable()->constrained('evaluation_templates')->nullOnDelete();
                $table->foreignId('type_id')->constrained('evaluation_form_types')->restrictOnDelete();
                $table->string('period_kind', 32);
                $table->unsignedTinyInteger('period_month')->nullable();
                $table->unsignedSmallInteger('period_year')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->boolean('auto_create_next')->default(false);
                $table->foreignId('manager_employee_id')->constrained('employees')->restrictOnDelete();
                $table->date('deadline');
                $table->string('evaluation_order', 32)->default('parallel');
                $table->boolean('use_weight')->default(true);
                $table->string('status', 32)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('form_code', 'eval_form_code_uq');
                $table->index(['status', 'deadline'], 'eval_form_status_deadline_idx');
                $table->index(['period_kind', 'period_year', 'period_month'], 'eval_form_period_idx');
                $table->index('template_id', 'eval_form_tpl_idx');
            });
        }

        if (! Schema::hasTable('evaluation_form_watchers')) {
            Schema::create('evaluation_form_watchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('evaluation_forms')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['form_id', 'employee_id'], 'eval_form_watcher_uq');
            });
        }

        if (! Schema::hasTable('evaluation_form_raters')) {
            Schema::create('evaluation_form_raters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('evaluation_forms')->cascadeOnDelete();
                $table->string('role_key', 64);
                $table->string('label');
                $table->decimal('weight_percent', 5, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['form_id', 'sort_order'], 'eval_form_rater_sort_idx');
            });
        }

        if (! Schema::hasTable('evaluation_form_fields')) {
            Schema::create('evaluation_form_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('evaluation_forms')->cascadeOnDelete();
                $table->string('field_key', 100);
                $table->string('label');
                $table->string('field_type', 32)->default('textarea');
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['form_id', 'field_key'], 'eval_form_field_key_uq');
                $table->index(['form_id', 'sort_order'], 'eval_form_field_sort_idx');
            });
        }

        if (! Schema::hasTable('evaluation_form_criteria')) {
            Schema::create('evaluation_form_criteria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('evaluation_forms')->cascadeOnDelete();
                $table->foreignId('criterion_id')->nullable()->constrained('evaluation_criteria')->nullOnDelete();
                $table->string('name');
                $table->decimal('weight', 8, 2)->default(0);
                $table->string('required_score_label', 255)->nullable();
                $table->string('evaluator_mode', 16)->default('all');
                $table->json('evaluator_role_keys')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['form_id', 'sort_order'], 'eval_form_crit_sort_idx');
            });
        }

        if (! Schema::hasTable('evaluation_form_assignees')) {
            Schema::create('evaluation_form_assignees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('evaluation_forms')->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->string('employee_code', 100)->nullable();
                $table->string('employee_name')->nullable();
                $table->string('department_code', 100)->nullable();
                $table->string('department_name')->nullable();
                $table->unsignedBigInteger('dept_head_employee_id');
                $table->unsignedBigInteger('direct_manager_employee_id');
                $table->unsignedBigInteger('board_employee_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('employee_id', 'eval_form_asg_emp_fk')
                    ->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('dept_head_employee_id', 'eval_form_asg_head_fk')
                    ->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('direct_manager_employee_id', 'eval_form_asg_mgr_fk')
                    ->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('board_employee_id', 'eval_form_asg_board_fk')
                    ->references('id')->on('employees')->nullOnDelete();

                $table->unique(['form_id', 'employee_id'], 'eval_form_assignee_uq');
                $table->index(['form_id', 'sort_order'], 'eval_form_assignee_sort_idx');
            });
        }

        $hasDefaultType = DB::table('evaluation_form_types')
            ->where('name', 'Đánh giá định kỳ')
            ->exists();

        if (! $hasDefaultType) {
            DB::table('evaluation_form_types')->insert([
                'name' => 'Đánh giá định kỳ',
                'sort_order' => 0,
                'is_active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_form_assignees');
        Schema::dropIfExists('evaluation_form_criteria');
        Schema::dropIfExists('evaluation_form_fields');
        Schema::dropIfExists('evaluation_form_raters');
        Schema::dropIfExists('evaluation_form_watchers');
        Schema::dropIfExists('evaluation_forms');
        Schema::dropIfExists('evaluation_form_types');
    }
};
