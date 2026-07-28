<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production DBs đã chạy migration cũ (cms_user_id) → rename sang hrm_user_id.
 * Fresh install (migration 2026_06_03 đã tạo hrm_user_id) → no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'cms_user_id')) {
            return;
        }

        if (Schema::hasColumn('employees', 'hrm_user_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['cms_user_id']);
                $table->dropColumn('cms_user_id');
            });

            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $table = Schema::getConnection()->getTablePrefix().'employees';

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['cms_user_id']);
        });

        if ($driver === 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('hrm_user_id')->nullable();
            });
            DB::table('employees')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('employees')
                        ->where('id', $row->id)
                        ->update(['hrm_user_id' => $row->cms_user_id ?? null]);
                }
            });
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('cms_user_id');
                $table->unique('hrm_user_id');
            });

            return;
        }

        DB::statement("ALTER TABLE `{$table}` CHANGE `cms_user_id` `hrm_user_id` BIGINT UNSIGNED NULL");

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('hrm_user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'hrm_user_id') || Schema::hasColumn('employees', 'cms_user_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $table = Schema::getConnection()->getTablePrefix().'employees';

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['hrm_user_id']);
        });

        if ($driver === 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('cms_user_id')->nullable();
            });
            DB::table('employees')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('employees')
                        ->where('id', $row->id)
                        ->update(['cms_user_id' => $row->hrm_user_id ?? null]);
                }
            });
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('hrm_user_id');
                $table->unique('cms_user_id');
            });

            return;
        }

        DB::statement("ALTER TABLE `{$table}` CHANGE `hrm_user_id` `cms_user_id` BIGINT UNSIGNED NULL");

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('cms_user_id');
        });
    }
};
