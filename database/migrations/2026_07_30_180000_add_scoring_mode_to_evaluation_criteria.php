<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add scoring_mode ('scale' | 'event') so a criterion can either use the
 * existing multi-level score_levels scale, or behave as a repeatable
 * per-occurrence bonus/penalty event (fixed points per occurrence, applied
 * N times per period, e.g. HR's "+6 điểm/lần" rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->string('scoring_mode', 16)->default('scale')->after('category');
            $table->integer('event_points')->nullable()->after('score_levels');
            $table->integer('event_max_per_period')->nullable()->after('event_points');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn(['scoring_mode', 'event_points', 'event_max_per_period']);
        });
    }
};
