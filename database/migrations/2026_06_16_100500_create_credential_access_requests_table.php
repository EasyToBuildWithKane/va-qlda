<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->json('requested_permissions');
            $table->text('reason')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['credential_id', 'status'], 'cred_req_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_access_requests');
    }
};
