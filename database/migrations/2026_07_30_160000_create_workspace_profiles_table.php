<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 100);
            $table->string('department_name');
            $table->foreignId('local_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('department_code', 'ws_profiles_dept_code_uq');
            $table->index('status', 'ws_profiles_status_idx');
            $table->index('local_department_id', 'ws_profiles_local_dept_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_profiles');
    }
};
