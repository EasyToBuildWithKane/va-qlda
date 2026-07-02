<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_proposal_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_purchase_proposal_id')->nullable()
                ->constrained('ai_purchase_proposals', indexName: 'ai_prop_scan_proposal_fk')
                ->nullOnDelete();
            $table->string('original_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('status', 16)->default('processing');
            $table->json('extracted_fields')->nullable();
            $table->longText('raw_text')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('pages')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('created_by')->constrained('system_accounts')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ai_proposal_scan_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ai_proposal_scan_id')
                ->constrained('ai_proposal_scans', indexName: 'ai_prop_scan_sig_scan_fk')
                ->cascadeOnDelete();
            $table->string('role', 32);
            $table->boolean('signed')->default(false);
            $table->string('signer_name')->nullable();
            $table->decimal('confidence', 4, 3)->default(0);
            $table->string('image_path')->nullable();
            $table->json('bbox')->nullable();
            $table->unsignedInteger('page')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_proposal_scan_signatures');
        Schema::dropIfExists('ai_proposal_scans');
    }
};
