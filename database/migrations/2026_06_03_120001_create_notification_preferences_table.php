<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_account_id')->unique()->constrained('system_accounts')->cascadeOnDelete();
            $table->json('disabled_types')->nullable();
            $table->boolean('channel_in_app')->default(true);
            $table->boolean('channel_email')->default(false);
            $table->boolean('channel_push')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
