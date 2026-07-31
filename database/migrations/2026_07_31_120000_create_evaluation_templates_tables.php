<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mẫu đánh giá: gắn tiêu chí từ catalog + nhật ký xuất Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_templates')) {
            Schema::create('evaluation_templates', function (Blueprint $table) {
                $table->id();
                $table->string('template_code', 100);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('position_code', 150)->nullable();
                $table->string('position_name')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('template_code', 'eval_tpl_code_uq');
                $table->index(['is_active', 'sort_order'], 'eval_tpl_active_sort_idx');
                $table->index('position_code', 'eval_tpl_pos_idx');
            });
        }

        if (! Schema::hasTable('evaluation_template_criteria')) {
            Schema::create('evaluation_template_criteria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
                $table->foreignId('criterion_id')->constrained('evaluation_criteria')->cascadeOnDelete();
                $table->decimal('weight', 8, 2)->default(1);
                $table->string('required_score_label', 255)->nullable();
                $table->boolean('include_in_total')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['template_id', 'criterion_id'], 'eval_tpl_crit_uq');
                $table->index(['template_id', 'sort_order'], 'eval_tpl_crit_sort_idx');
            });
        }

        if (! Schema::hasTable('evaluation_template_export_logs')) {
            Schema::create('evaluation_template_export_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exported_by')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->string('scope', 32);
                $table->string('format', 16);
                $table->unsignedInteger('row_count')->default(0);
                $table->json('columns')->nullable();
                $table->json('filters')->nullable();
                $table->string('filename')->nullable();
                $table->timestamps();

                $table->index(['exported_by', 'created_at'], 'eval_tpl_exp_actor_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_template_export_logs');
        Schema::dropIfExists('evaluation_template_criteria');
        Schema::dropIfExists('evaluation_templates');
    }
};
