<?php

namespace App\Http\Resources\Evaluation;

use App\Models\Evaluation\EvaluationConfig;
use App\Models\Evaluation\EvaluationCriterion;
use App\Support\Enums\EvaluationTemplateType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EvaluationConfig */
class EvaluationConfigResource extends JsonResource
{
    public static function criterionPayload(EvaluationCriterion $c): array
    {
        return [
            'id' => $c->id,
            'criteria_code' => $c->criteria_code,
            'criteria_name' => $c->criteria_name,
            'category' => $c->category,
            'description' => $c->description,
            'point_value' => $c->point_value,
            'max_points' => $c->max_points,
            'max_frequency' => $c->max_frequency,
            'weight' => $c->weight !== null ? (float) $c->weight : null,
            'required_score' => $c->required_score,
            'importance' => $c->importance,
            'sort_order' => $c->sort_order,
            'is_active' => (bool) $c->is_active,
        ];
    }

    /**
     * @param  iterable<EvaluationCriterion>  $criteria
     * @return list<array{category:string, criteria:list<array<string,mixed>>}>
     */
    public static function groupCriteria(iterable $criteria): array
    {
        $groups = [];
        foreach ($criteria as $c) {
            $cat = $c->category ?: 'Khác';
            if (! isset($groups[$cat])) {
                $groups[$cat] = ['category' => $cat, 'criteria' => []];
            }
            $groups[$cat]['criteria'][] = self::criterionPayload($c);
        }

        return array_values($groups);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvaluationConfig $config */
        $config = $this->resource;
        $type = $config->template_type instanceof EvaluationTemplateType
            ? $config->template_type
            : EvaluationTemplateType::from((string) $config->template_type);

        $payload = [
            'id' => $config->id,
            'department_code' => $config->department_code,
            'department_name' => $config->department_name,
            'local_department_id' => $config->local_department_id,
            'template_type' => $type->value,
            'template_type_label' => $type->label(),
            'config_name' => $config->config_name,
            'description' => $config->description,
            'effective_from' => $config->effective_from?->toDateString(),
            'effective_to' => $config->effective_to?->toDateString(),
            'base_score' => $config->base_score,
            'is_active' => (bool) $config->is_active,
            'created_by' => $config->created_by,
            'created_at' => $config->created_at?->toIso8601String(),
            'updated_at' => $config->updated_at?->toIso8601String(),
            'criteria_count' => $config->relationLoaded('criteria')
                ? $config->criteria->count()
                : ($config->criteria_count ?? null),
        ];

        if ($config->relationLoaded('criteria')) {
            $payload['criteria'] = $config->criteria->map(fn ($c) => self::criterionPayload($c))->values()->all();
            $payload['criteria_groups'] = self::groupCriteria($config->criteria);
        }

        if ($config->relationLoaded('creator') && $config->creator) {
            $payload['creator'] = [
                'id' => $config->creator->id,
                'display_name' => $config->creator->display_name,
            ];
        }

        return $payload;
    }
}
