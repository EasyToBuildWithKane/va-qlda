<?php

namespace App\Http\Resources\Evaluation;

use App\Models\Evaluation\EvaluationCriterion;
use App\Support\Enums\EvaluationCriterionScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EvaluationCriterion */
class EvaluationCriterionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvaluationCriterion $c */
        $c = $this->resource;
        $scope = $c->scope instanceof EvaluationCriterionScope
            ? $c->scope
            : EvaluationCriterionScope::from((string) $c->scope);

        $levels = $c->normalizedScoreLevels();

        $payload = [
            'id' => $c->id,
            'scope' => $scope->value,
            'scope_label' => $scope->label(),
            'department_code' => $c->department_code,
            'department_name' => $c->department_name,
            'local_department_id' => $c->local_department_id,
            'criteria_code' => $c->criteria_code,
            'criteria_name' => $c->criteria_name,
            'display_name' => $c->displayName(),
            'category' => $c->category,
            'description' => $c->description,
            'allow_half_score' => (bool) $c->allow_half_score,
            'score_levels' => $levels,
            'score_levels_count' => count($levels),
            'sort_order' => $c->sort_order,
            'is_active' => (bool) $c->is_active,
            'created_by' => $c->created_by,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];

        if ($c->relationLoaded('creator') && $c->creator) {
            $payload['creator'] = [
                'id' => $c->creator->id,
                'display_name' => $c->creator->display_name,
            ];
        }

        return $payload;
    }
}
