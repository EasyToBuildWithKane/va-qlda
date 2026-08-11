<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_profiles', function (Blueprint $table) {
            $table->json('enabled_nav_groups')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_profiles', function (Blueprint $table) {
            $table->dropColumn('enabled_nav_groups');
        });
    }
};
