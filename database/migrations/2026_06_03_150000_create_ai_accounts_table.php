<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tool_name');
            $table->string('license_type');
            $table->string('license_key')->nullable();
            $table->string('group_function', 32);
            $table->string('email_registered');
            $table->date('purchase_date');
            $table->date('expiry_date');
            $table->unsignedBigInteger('cost_amount');
            $table->string('cost_unit', 16);
            $table->unsignedInteger('seats')->nullable();
            $table->string('status', 24);
            $table->unsignedSmallInteger('notify_before_days')->default(14);
            $table->timestamp('last_reminded_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('group_function');
            $table->index('status');
            $table->index('expiry_date');
            $table->index(['status', 'last_reminded_at'], 'ai_acct_status_rem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_accounts');
    }
};
