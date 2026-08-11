<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_accounts', 'purchase_type')) {
                $table->string('purchase_type', 16)->default('new')->after('purchase_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('ai_accounts', 'purchase_type')) {
                $table->dropColumn('purchase_type');
            }
        });
    }
};
