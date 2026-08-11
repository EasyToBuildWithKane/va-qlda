<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('cooperation_status', 32)
                ->default('active')
                ->after('is_active');
            $table->index('cooperation_status', 'vendors_coop_status_idx');
        });

        // Backfill từ is_active hiện có.
        DB::table('vendors')->where('is_active', false)->update(['cooperation_status' => 'inactive']);
        DB::table('vendors')->where('is_active', true)->whereNull('cooperation_status')->update(['cooperation_status' => 'active']);
        DB::table('vendors')->where('is_active', true)->where('cooperation_status', '')->update(['cooperation_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex('vendors_coop_status_idx');
            $table->dropColumn('cooperation_status');
        });
    }
};
