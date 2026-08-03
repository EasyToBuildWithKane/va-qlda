<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi chức danh/cấp bậc, tiêu chí tùy chỉnh, trường form phụ trên mẫu đánh giá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_template_targets')) {
            Schema::create('evaluation_template_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
                $table->string('kind', 32); // title | rank
                $table->string('code', 150);
                $table->string('name');
                $table->string('hrm_uuid', 64)->nullable();
                $table->string('source', 32)->default('directory');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['template_id', 'kind', 'code'], 'eval_tpl_tgt_uq');
                $table->index(['template_id', 'kind'], 'eval_tpl_tgt_kind_idx');
            });
        }

        if (! Schema::hasTable('evaluation_template_custom_criteria')) {
            Schema::create('evaluation_template_custom_criteria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
                $table->string('custom_code', 100)->nullable();
                $table->string('custom_name');
                $table->string('custom_category', 100)->nullable();
                $table->text('custom_description')->nullable();
                $table->decimal('weight', 8, 2)->default(1);
                $table->string('required_score_label', 255)->nullable();
                $table->boolean('include_in_total')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['template_id', 'sort_order'], 'eval_tpl_cc_sort_idx');
            });
        }

        if (! Schema::hasTable('evaluation_template_fields')) {
            Schema::create('evaluation_template_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
                $table->string('field_key', 100);
                $table->string('label');
                $table->string('field_type', 32);
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->string('placeholder')->nullable();
                $table->string('help_text', 500)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['template_id', 'field_key'], 'eval_tpl_field_key_uq');
                $table->index(['template_id', 'sort_order'], 'eval_tpl_field_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_template_fields');
        Schema::dropIfExists('evaluation_template_custom_criteria');
        Schema::dropIfExists('evaluation_template_targets');
    }
};
