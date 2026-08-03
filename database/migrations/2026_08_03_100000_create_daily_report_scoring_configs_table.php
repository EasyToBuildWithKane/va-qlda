<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_report_scoring_configs')) {
            return;
        }

        Schema::create('daily_report_scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 64);
            $table->string('department_name')->nullable();
            $table->unsignedBigInteger('local_department_id')->nullable();
            $table->json('weights');
            $table->decimal('kaizen_bonus_max', 4, 2)->default(2.0);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('department_code', 'dr_scoring_cfg_dept_uq');
            $table->index('status', 'dr_scoring_cfg_status_idx');

            $table->foreign('local_department_id', 'dr_scoring_cfg_local_dept_fk')
                ->references('id')->on('departments')->nullOnDelete();
            $table->foreign('updated_by', 'dr_scoring_cfg_updated_by_fk')
                ->references('id')->on('system_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_scoring_configs');
    }
};
