<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production có thể đã migrate `contracts` trước khi có cột root_contract_id (phụ lục / gia hạn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'root_contract_id')) {
                $table->unsignedBigInteger('root_contract_id')->nullable()->after('category_id');
                $table->foreign('root_contract_id')->references('id')->on('contracts')->nullOnDelete();
                $table->index('root_contract_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'root_contract_id')) {
                $table->dropForeign(['root_contract_id']);
                $table->dropIndex(['root_contract_id']);
                $table->dropColumn('root_contract_id');
            }
        });
    }
};
