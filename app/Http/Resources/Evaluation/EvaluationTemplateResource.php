<?php

namespace App\Http\Resources\Evaluation;

use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateCriterion;
use App\Models\Evaluation\EvaluationTemplateCustomCriterion;
use App\Models\Evaluation\EvaluationTemplateField;
use App\Models\Evaluation\EvaluationTemplateTarget;
use App\Support\Enums\EvaluationTemplateFieldType;
use App\Support\Enums\EvaluationTemplateTargetKind;
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

        $catalogCriteria = [];
        if ($t->relationLoaded('templateCriteria')) {
            $catalogCriteria = $t->templateCriteria
                ->map(function (EvaluationTemplateCriterion $line) {
                    $criterion = $line->relationLoaded('criterion') ? $line->criterion : null;

                    return [
                        'id' => $line->id,
                        'source' => 'catalog',
                        'criterion_id' => $line->criterion_id,
                        'weight' => $line->weight,
                        'required_score_label' => $line->required_score_label,
                        'include_in_total' => (bool) $line->include_in_total,
                        'sort_order' => $line->sort_order,
                        'criteria_code' => $criterion?->criteria_code,
                        'criteria_name' => $criterion?->criteria_name,
                        'category' => $criterion?->category,
                        'is_active' => $criterion ? (bool) $criterion->is_active : null,
                        'is_custom' => false,
                    ];
                })
                ->values()
                ->all();
        }

        $customCriteria = [];
        if ($t->relationLoaded('customCriteria')) {
            $customCriteria = $t->customCriteria
                ->map(function (EvaluationTemplateCustomCriterion $line) {
                    return [
                        'id' => $line->id,
                        'source' => 'custom',
                        'criterion_id' => null,
                        'weight' => $line->weight,
                        'required_score_label' => $line->required_score_label,
                        'include_in_total' => (bool) $line->include_in_total,
                        'sort_order' => $line->sort_order,
                        'criteria_code' => $line->custom_code,
                        'criteria_name' => $line->custom_name,
                        'category' => $line->custom_category,
                        'description' => $line->custom_description,
                        'is_active' => true,
                        'is_custom' => true,
                        'custom_code' => $line->custom_code,
                        'custom_name' => $line->custom_name,
                        'custom_category' => $line->custom_category,
                        'custom_description' => $line->custom_description,
                    ];
                })
                ->values()
                ->all();
        }

        $criteriaLines = collect([...$catalogCriteria, ...$customCriteria])
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();

        $targets = [];
        $titleLabels = [];
        $rankLabels = [];
        if ($t->relationLoaded('targets')) {
            $targets = $t->targets->map(function (EvaluationTemplateTarget $row) {
                $kind = $row->kind instanceof EvaluationTemplateTargetKind
                    ? $row->kind
                    : EvaluationTemplateTargetKind::from((string) $row->kind);

                return [
                    'id' => $row->id,
                    'kind' => $kind->value,
                    'kind_label' => $kind->label(),
                    'code' => $row->code,
                    'name' => $row->name,
                    'hrm_uuid' => $row->hrm_uuid,
                    'source' => $row->source,
                    'sort_order' => $row->sort_order,
                ];
            })->values()->all();

            foreach ($targets as $target) {
                if ($target['kind'] === EvaluationTemplateTargetKind::Title->value) {
                    $titleLabels[] = $target['name'];
                } elseif ($target['kind'] === EvaluationTemplateTargetKind::Rank->value) {
                    $rankLabels[] = $target['name'];
                }
            }
        }

        $fields = [];
        if ($t->relationLoaded('fields')) {
            $fields = $t->fields->map(function (EvaluationTemplateField $field) {
                $type = $field->field_type instanceof EvaluationTemplateFieldType
                    ? $field->field_type
                    : EvaluationTemplateFieldType::from((string) $field->field_type);

                return [
                    'id' => $field->id,
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'field_type' => $type->value,
                    'field_type_label' => $type->label(),
                    'options' => $field->options ?? [],
                    'is_required' => (bool) $field->is_required,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'sort_order' => $field->sort_order,
                ];
            })->values()->all();
        }

        $positionDisplay = $t->position_name;
        if ($titleLabels !== [] || $rankLabels !== []) {
            $parts = [];
            if ($titleLabels !== []) {
                $parts[] = implode(', ', $titleLabels);
            }
            if ($rankLabels !== []) {
                $parts[] = 'Cấp: '.implode(', ', $rankLabels);
            }
            $positionDisplay = implode(' · ', $parts);
        }

        $payload = [
            'id' => $t->id,
            'template_code' => $t->template_code,
            'name' => $t->name,
            'description' => $t->description,
            'position_code' => $t->position_code,
            'position_name' => $positionDisplay ?: $t->position_name,
            'titles' => array_values(array_filter($targets, fn ($x) => $x['kind'] === 'title')),
            'ranks' => array_values(array_filter($targets, fn ($x) => $x['kind'] === 'rank')),
            'targets' => $targets,
            'sort_order' => $t->sort_order,
            'is_active' => (bool) $t->is_active,
            'criteria_count' => count($criteriaLines),
            'criteria' => $criteriaLines,
            'criteria_labels' => collect($criteriaLines)
                ->map(fn (array $c) => trim((string) ($c['criteria_name'] ?? '')))
                ->filter()
                ->values()
                ->all(),
            'fields' => $fields,
            'fields_count' => count($fields),
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
