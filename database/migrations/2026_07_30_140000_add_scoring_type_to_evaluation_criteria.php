<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add scoring_type (scale | points) with numeric bonus/penalty for point-style criteria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->string('scoring_type', 32)->default('scale')->after('category');
            $table->integer('point_bonus')->nullable()->after('allow_half_score');
            $table->integer('point_penalty')->nullable()->after('point_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn(['scoring_type', 'point_bonus', 'point_penalty']);
        });
    }
};
