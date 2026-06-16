<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->text('encrypted_password');
            $table->foreignId('changed_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->text('notes')->nullable();

            $table->index(['credential_id', 'changed_at'], 'cred_pwd_hist_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_password_histories');
    }
};
