<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('congnghe_software_proposals')) {
            Schema::create('congnghe_software_proposals', function (Blueprint $table) {
                $table->id();
                $table->string('reference_code', 24)->nullable()->unique();
                $table->foreignId('system_account_id')->nullable()->constrained('system_accounts')->nullOnDelete();
                $table->string('submitter_name', 120);
                $table->string('submitter_email', 255);
                $table->string('department', 160);
                $table->string('title', 200);
                $table->text('content');
                $table->string('status', 32)->default('new');
                $table->timestamp('email_sent_at')->nullable();
                $table->string('email_error', 500)->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('created_at');
                $table->index('submitter_email');
            });
        }

        if (! Schema::hasTable('congnghe_software_proposal_attachments')) {
            Schema::create('congnghe_software_proposal_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('congnghe_software_proposal_id');
                $table->foreign('congnghe_software_proposal_id', 'cn_sw_prop_att_prop_fk')
                    ->references('id')
                    ->on('congnghe_software_proposals')
                    ->cascadeOnDelete();
                $table->string('original_name');
                $table->string('path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->boolean('is_image')->default(false);
                $table->timestamps();

                $table->index('congnghe_software_proposal_id', 'cn_sw_prop_att_prop_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('congnghe_software_proposal_attachments');
        Schema::dropIfExists('congnghe_software_proposals');
    }
};
