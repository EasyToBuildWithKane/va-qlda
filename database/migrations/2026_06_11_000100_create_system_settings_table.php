<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            // Full setting key, namespaced by group, e.g. "telegram.enabled".
            $table->string('key')->unique();
            // JSON-encoded value (scalar / list / matrix). Decoded by SettingsRepository.
            $table->longText('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
