<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gỡ mẫu phiếu hệ thống HCNS / CNTT và catalog evaluation_templates.
 * Cấu hình đánh giá chỉ còn CRUD tiêu chí thủ công theo loại engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evaluation_configs') && Schema::hasColumn('evaluation_configs', 'template_id')) {
            Schema::table('evaluation_configs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('template_id');
            });
        }

        Schema::dropIfExists('evaluation_template_criteria');
        Schema::dropIfExists('evaluation_templates');
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluation_templates')) {
            Schema::create('evaluation_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('template_type', 32);
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index('template_type', 'eval_tpl_type_idx');
            });
        }

        if (! Schema::hasTable('evaluation_template_criteria')) {
            Schema::create('evaluation_template_criteria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
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

                $table->unique(['template_id', 'criteria_code'], 'eval_tpl_crit_code_uq');
                $table->index(['template_id', 'sort_order'], 'eval_tpl_crit_sort_idx');
            });
        }

        if (Schema::hasTable('evaluation_configs') && ! Schema::hasColumn('evaluation_configs', 'template_id')) {
            Schema::table('evaluation_configs', function (Blueprint $table) {
                $table->foreignId('template_id')->nullable()->after('local_department_id')
                    ->constrained('evaluation_templates')->nullOnDelete();
            });
        }
    }
};
