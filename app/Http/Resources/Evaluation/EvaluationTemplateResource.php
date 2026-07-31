<?php

namespace App\Http\Resources\Evaluation;

use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateCriterion;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EvaluationTemplate */
class EvaluationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvaluationTemplate $t */
        $t = $this->resource;

        $criteriaLines = [];
        if ($t->relationLoaded('templateCriteria')) {
            $criteriaLines = $t->templateCriteria
                ->map(function (EvaluationTemplateCriterion $line) {
                    $criterion = $line->relationLoaded('criterion') ? $line->criterion : null;

                    return [
                        'id' => $line->id,
                        'criterion_id' => $line->criterion_id,
                        'weight' => $line->weight,
                        'required_score_label' => $line->required_score_label,
                        'include_in_total' => (bool) $line->include_in_total,
                        'sort_order' => $line->sort_order,
                        'criteria_code' => $criterion?->criteria_code,
                        'criteria_name' => $criterion?->criteria_name,
                        'category' => $criterion?->category,
                        'is_active' => $criterion ? (bool) $criterion->is_active : null,
                    ];
                })
                ->values()
                ->all();
        }

        $payload = [
            'id' => $t->id,
            'template_code' => $t->template_code,
            'name' => $t->name,
            'description' => $t->description,
            'position_code' => $t->position_code,
            'position_name' => $t->position_name,
            'sort_order' => $t->sort_order,
            'is_active' => (bool) $t->is_active,
            'criteria_count' => $t->relationLoaded('templateCriteria')
                ? $t->templateCriteria->count()
                : (int) ($t->template_criteria_count ?? 0),
            'criteria' => $criteriaLines,
            'criteria_labels' => collect($criteriaLines)
                ->map(fn (array $c) => trim((string) ($c['criteria_name'] ?? '')))
                ->filter()
                ->values()
                ->all(),
            'created_by' => $t->created_by,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];

        if ($t->relationLoaded('creator') && $t->creator) {
            $payload['creator'] = [
                'id' => $t->creator->id,
                'display_name' => $t->creator->display_name,
                'avatar' => PublicMediaUrl::fromPublicDisk(
                    $t->creator->relationLoaded('employee')
                        ? $t->creator->employee?->avatar_path
                        : null
                ),
            ];
        }

        return $payload;
    }
}
