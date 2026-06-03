<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_purchase_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tool_name');
            $table->string('group_function', 32);
            $table->string('license_type', 64);
            $table->unsignedBigInteger('cost_amount');
            $table->string('cost_unit', 16);
            $table->unsignedInteger('seats')->nullable();
            $table->text('justification');
            $table->string('status', 16)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('group_function');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_purchase_proposals');
    }
};
