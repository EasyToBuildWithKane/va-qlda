<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 100);
            $table->string('department_name');
            $table->foreignId('local_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('template_type', 32);
            $table->string('config_name');
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('base_score')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['department_code', 'template_type', 'effective_from'],
                'eval_cfg_dept_type_from_uq'
            );
            $table->index(['department_code', 'is_active'], 'eval_cfg_dept_active_idx');
            $table->index('template_type', 'eval_cfg_type_idx');
            $table->index(['effective_from', 'effective_to'], 'eval_cfg_effect_idx');
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('evaluation_configs')->cascadeOnDelete();
            $table->string('criteria_code', 100);
            $table->string('criteria_name');
            $table->string('category', 100);
            $table->text('description')->nullable();
            $table->integer('point_value')->nullable();
            $table->unsignedInteger('max_points')->nullable();
            $table->unsignedInteger('max_frequency')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->unsignedTinyInteger('required_score')->nullable();
            $table->string('importance', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['config_id', 'criteria_code'], 'eval_crit_code_uq');
            $table->index(['config_id', 'sort_order'], 'eval_crit_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('evaluation_configs');
    }
};
