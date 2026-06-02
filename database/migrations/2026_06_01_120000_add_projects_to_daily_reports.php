<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the list of projects a member worked on for the day. Until a full
 * Projects module lands, each entry is a lightweight {id, name} object picked
 * from App\Support\ProjectCatalog. The legacy single `project_id` column is
 * kept for backward compatibility (set to the first selected project).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->json('projects')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn('projects');
        });
    }
};
