<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('blockers', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        $table = Schema::getConnection()->getTablePrefix().'blockers';
        DB::statement("ALTER TABLE `{$table}` MODIFY `project_id` BIGINT UNSIGNED NULL");

        Schema::table('blockers', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('blockers', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        $table = Schema::getConnection()->getTablePrefix().'blockers';
        DB::statement("ALTER TABLE `{$table}` MODIFY `project_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('blockers', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
