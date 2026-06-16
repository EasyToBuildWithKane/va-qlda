<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->string('action', 32);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['credential_id', 'created_at'], 'cred_audit_cred_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_audit_logs');
    }
};
