<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi dự án thuộc 1 phòng ban (độc lập với loại/vòng đời dự án).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('manager_id')
                ->constrained('departments')->nullOnDelete();
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
