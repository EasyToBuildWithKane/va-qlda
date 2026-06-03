<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_account_password_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_account_id')->unique()->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_account_password_viewers');
    }
};
