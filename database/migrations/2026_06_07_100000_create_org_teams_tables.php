<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('org_teams')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('level');
            $table->foreignId('leader_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index('level');
            $table->index('is_active');
        });

        Schema::create('org_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_team_id')->constrained('org_teams')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('branch', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['org_team_id', 'employee_id'], 'org_team_member_uq');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_team_members');
        Schema::dropIfExists('org_teams');
    }
};
