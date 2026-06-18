<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congnghe_software_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('congnghe_software_proposals', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('congnghe_software_proposals', 'rejection_email_sent_at')) {
                $table->timestamp('rejection_email_sent_at')->nullable()->after('email_error');
            }
            if (! Schema::hasColumn('congnghe_software_proposals', 'rejection_email_error')) {
                $table->string('rejection_email_error', 500)->nullable()->after('rejection_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('congnghe_software_proposals', function (Blueprint $table): void {
            if (Schema::hasColumn('congnghe_software_proposals', 'rejection_email_error')) {
                $table->dropColumn('rejection_email_error');
            }
            if (Schema::hasColumn('congnghe_software_proposals', 'rejection_email_sent_at')) {
                $table->dropColumn('rejection_email_sent_at');
            }
            if (Schema::hasColumn('congnghe_software_proposals', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
