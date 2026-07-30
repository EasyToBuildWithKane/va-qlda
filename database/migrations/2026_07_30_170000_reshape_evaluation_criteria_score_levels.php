<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace fixed score_1…5 + points mode with flexible score_levels JSON
 * (label + weight int per level). Drop scoring_type / point_bonus / point_penalty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->json('score_levels')->nullable()->after('allow_half_score');
        });

        $defaults = [
            ['label' => 'Không đáp ứng', 'weight' => 1],
            ['label' => 'Cần cố gắng hơn', 'weight' => 2],
            ['label' => 'Đạt yêu cầu', 'weight' => 3],
            ['label' => 'Tốt', 'weight' => 4],
            ['label' => 'Rất tốt', 'weight' => 5],
        ];

        $rows = DB::table('evaluation_criteria')->select([
            'id',
            'scoring_type',
            'point_bonus',
            'point_penalty',
            'score_1',
            'score_2',
            'score_3',
            'score_4',
            'score_5',
        ])->get();

        foreach ($rows as $row) {
            $levels = [];

            if (($row->scoring_type ?? 'scale') === 'points') {
                $bonus = (int) ($row->point_bonus ?? 0);
                $penalty = (int) ($row->point_penalty ?? 0);
                $levels = [
                    ['label' => 'Không đạt', 'weight' => $penalty > 0 ? -$penalty : 0],
                    ['label' => 'Đạt', 'weight' => $bonus],
                ];
            } else {
                foreach ([1, 2, 3, 4, 5] as $n) {
                    $key = "score_{$n}";
                    $label = trim((string) ($row->{$key} ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $levels[] = [
                        'label' => $label,
                        'weight' => $n,
                    ];
                }
            }

            if ($levels === []) {
                $levels = $defaults;
            }

            DB::table('evaluation_criteria')->where('id', $row->id)->update([
                'score_levels' => json_encode($levels, JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn([
                'scoring_type',
                'point_bonus',
                'point_penalty',
                'score_1',
                'score_2',
                'score_3',
                'score_4',
                'score_5',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->string('scoring_type', 32)->default('scale')->after('category');
            $table->integer('point_bonus')->nullable()->after('allow_half_score');
            $table->integer('point_penalty')->nullable()->after('point_bonus');
            $table->string('score_1')->nullable()->after('point_penalty');
            $table->string('score_2')->nullable()->after('score_1');
            $table->string('score_3')->nullable()->after('score_2');
            $table->string('score_4')->nullable()->after('score_3');
            $table->string('score_5')->nullable()->after('score_4');
        });

        $rows = DB::table('evaluation_criteria')->select(['id', 'score_levels'])->get();
        $fallback = [
            1 => 'Không đáp ứng',
            2 => 'Cần cố gắng hơn',
            3 => 'Đạt yêu cầu',
            4 => 'Tốt',
            5 => 'Rất tốt',
        ];

        foreach ($rows as $row) {
            $levels = json_decode((string) $row->score_levels, true);
            if (! is_array($levels)) {
                $levels = [];
            }

            $update = [
                'scoring_type' => 'scale',
                'point_bonus' => null,
                'point_penalty' => null,
            ];

            for ($n = 1; $n <= 5; $n++) {
                $label = $levels[$n - 1]['label'] ?? $fallback[$n];
                $update["score_{$n}"] = is_string($label) && $label !== '' ? $label : $fallback[$n];
            }

            DB::table('evaluation_criteria')->where('id', $row->id)->update($update);
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn('score_levels');
        });
    }
};
