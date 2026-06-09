<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_attachments', 'external_url')) {
                $table->string('external_url', 2048)->nullable()->after('path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('project_attachments', 'external_url')) {
                $table->dropColumn('external_url');
            }
        });
    }
};
