<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production có thể đã tạo bảng contracts trước khi có cột billing_cycle (Chu kỳ).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'billing_cycle')) {
                $table->string('billing_cycle', 16)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'billing_cycle')) {
                $table->dropColumn('billing_cycle');
            }
        });
    }
};
