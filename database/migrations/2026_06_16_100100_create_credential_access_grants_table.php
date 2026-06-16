<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->json('permissions');
            $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['credential_id', 'account_id'], 'cred_grant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_access_grants');
    }
};
