<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSO HRM → Workspace: claim `employee_uuid` trong JWT HRM map vào cột này.
 * Song song với `hrm_user_id` (id legacy) — uuid là định danh bền khi HRM
 * cutover sang schema mới (docs/integrations/workspace.md phía va-hrm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->char('hrm_employee_uuid', 36)->nullable()->unique()->after('hrm_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['hrm_employee_uuid']);
            $table->dropColumn('hrm_employee_uuid');
        });
    }
};
