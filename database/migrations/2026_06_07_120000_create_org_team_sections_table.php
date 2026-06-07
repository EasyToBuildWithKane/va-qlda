<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_team_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_team_id')->constrained('org_teams')->cascadeOnDelete();
            $table->string('title', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['org_team_id', 'sort_order'], 'org_team_section_sort_idx');
        });

        Schema::table('org_team_members', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('org_team_id')
                ->constrained('org_team_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });

        Schema::dropIfExists('org_team_sections');
    }
};
