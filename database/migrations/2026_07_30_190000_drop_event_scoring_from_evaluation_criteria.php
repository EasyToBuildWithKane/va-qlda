<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove event / recurring scoring mode — criteria use score_levels (scale) only.
 * Existing event rows are converted to a 2-level scale before columns drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evaluation_criteria', 'scoring_mode')) {
            return;
        }

        $rows = DB::table('evaluation_criteria')
            ->where('scoring_mode', 'event')
            ->get(['id', 'event_points', 'event_max_per_period', 'description', 'score_levels']);

        foreach ($rows as $row) {
            $points = (int) ($row->event_points ?? 0);
            $max = $row->event_max_per_period;
            $maxNote = $max !== null && $max !== ''
                ? ' Tối đa '.((int) $max).' điểm/kỳ.'
                : '';

            $levels = [
                [
                    'code' => 'M1',
                    'label' => 'Không ghi nhận',
                    'description' => '',
                    'weight' => 0,
                ],
                [
                    'code' => 'M2',
                    'label' => 'Ghi nhận',
                    'description' => trim($maxNote),
                    'weight' => $points,
                ],
            ];

            $description = trim((string) ($row->description ?? ''));
            if ($maxNote !== '' && $description !== '' && ! str_contains($description, 'Tối đa')) {
                $description = rtrim($description, '.').'.'.rtrim($maxNote);
            } elseif ($maxNote !== '' && $description === '') {
                $description = trim($maxNote);
            }

            DB::table('evaluation_criteria')->where('id', $row->id)->update([
                'scoring_mode' => 'scale',
                'score_levels' => json_encode($levels, JSON_UNESCAPED_UNICODE),
                'description' => $description !== '' ? $description : null,
                'allow_half_score' => false,
                'event_points' => null,
                'event_max_per_period' => null,
            ]);
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn(['scoring_mode', 'event_points', 'event_max_per_period']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('evaluation_criteria', 'scoring_mode')) {
            return;
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->string('scoring_mode', 16)->default('scale')->after('category');
            $table->integer('event_points')->nullable()->after('score_levels');
            $table->integer('event_max_per_period')->nullable()->after('event_points');
        });
    }
};
