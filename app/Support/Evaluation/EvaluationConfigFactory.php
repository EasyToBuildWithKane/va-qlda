<?php

namespace App\Support\Evaluation;

use App\Models\Evaluation\EvaluationConfig;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateCriterion;
use Illuminate\Support\Facades\DB;

final class EvaluationConfigFactory
{
    /**
     * Copy tiêu chí từ mẫu vào cấu hình (thay thế toàn bộ criteria hiện có nếu $replace).
     */
    public function copyFromTemplate(EvaluationConfig $config, EvaluationTemplate $template, bool $replace = true): void
    {
        DB::transaction(function () use ($config, $template, $replace) {
            if ($replace) {
                $config->criteria()->delete();
            }

            $template->loadMissing('criteria');

            $rows = $template->criteria
                ->filter(fn (EvaluationTemplateCriterion $c) => $c->is_active)
                ->values()
                ->map(fn (EvaluationTemplateCriterion $c, int $i) => [
                    'config_id' => $config->id,
                    'criteria_code' => $c->criteria_code,
                    'criteria_name' => $c->criteria_name,
                    'category' => $c->category,
                    'description' => $c->description,
                    'point_value' => $c->point_value,
                    'max_points' => $c->max_points,
                    'max_frequency' => $c->max_frequency,
                    'weight' => $c->weight,
                    'required_score' => $c->required_score,
                    'importance' => $c->importance,
                    'sort_order' => $c->sort_order ?: ($i + 1),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows !== []) {
                EvaluationCriterion::query()->insert($rows);
            }

            $config->forceFill([
                'template_id' => $template->id,
                'template_type' => $template->template_type,
            ])->save();
        });
    }
}
