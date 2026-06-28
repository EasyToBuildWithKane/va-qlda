<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('status', 16);
            $table->json('snapshot');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['weekly_report_id', 'version_number'], 'wrv_report_version_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_report_versions');
    }
};
