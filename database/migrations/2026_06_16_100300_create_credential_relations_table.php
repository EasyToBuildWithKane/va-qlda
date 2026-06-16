<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('credentials')->cascadeOnDelete();
            $table->string('relation_type', 32);
            $table->string('label', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_id', 'target_id', 'relation_type'], 'cred_rel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_relations');
    }
};
