<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blockers', function (Blueprint $table) {
            $table->json('evidence_links')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('blockers', function (Blueprint $table) {
            $table->dropColumn('evidence_links');
        });
    }
};
