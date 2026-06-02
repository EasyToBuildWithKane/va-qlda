<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phạm vi áp dụng + khu vực/phòng ban áp dụng + ngân sách thực tế.
 *
 * - scope: Hội sở / Toàn hệ thống / Khu vực / Phòng ban (độc lập với department_id phụ trách).
 * - scope_regions: danh sách khu vực (khi scope = regional).
 * - scope_departments: danh sách phòng ban (khi scope = departmental).
 * - actual_budget: ngân sách thực tế, song song với budget (dự kiến).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('scope', 30)->default('headquarters')->after('type');
            $table->json('scope_regions')->nullable()->after('scope');
            $table->json('scope_departments')->nullable()->after('scope_regions');
            $table->decimal('actual_budget', 14, 2)->nullable()->after('budget');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn(['scope', 'scope_regions', 'scope_departments', 'actual_budget']);
        });
    }
};
