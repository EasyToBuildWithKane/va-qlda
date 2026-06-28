<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->string('section', 24);
            $table->longText('content')->nullable();
            $table->longText('ai_content')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['weekly_report_id', 'section'], 'wrs_report_section_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_report_sections');
    }
};
