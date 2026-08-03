<?php

namespace App\Http\Resources\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormAssignee;
use App\Models\Evaluation\EvaluationFormCriterion;
use App\Models\Evaluation\EvaluationFormField;
use App\Models\Evaluation\EvaluationFormRater;
use App\Models\Evaluation\EvaluationFormWatcher;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormPeriodKind;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EvaluationForm */
class EvaluationFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvaluationForm $form */
        $form = $this->resource;

        $periodKind = $form->period_kind instanceof EvaluationFormPeriodKind
            ? $form->period_kind
            : EvaluationFormPeriodKind::tryFrom((string) $form->period_kind);

        $order = $form->evaluation_order instanceof EvaluationFormOrder
            ? $form->evaluation_order
            : EvaluationFormOrder::tryFrom((string) $form->evaluation_order);

        $status = $form->status instanceof EvaluationFormStatus
            ? $form->status
            : EvaluationFormStatus::tryFrom((string) $form->status);

        $watcherIds = [];
        $watchers = [];
        if ($form->relationLoaded('watchers')) {
            $watcherIds = $form->watchers->pluck('employee_id')->map(fn ($id) => (int) $id)->values()->all();
            $watchers = $form->watchers->map(function (EvaluationFormWatcher $row) {
                $employee = $row->relationLoaded('employee') ? $row->employee : null;

                return [
                    'id' => $row->id,
                    'employee_id' => $row->employee_id,
                    'name' => $employee?->full_name,
                    'code' => $employee?->code,
                ];
            })->values()->all();
        }

        $raters = [];
        if ($form->relationLoaded('raters')) {
            $raters = $form->raters->map(fn (EvaluationFormRater $row) => [
                'id' => $row->id,
                'role_key' => $row->role_key,
                'label' => $row->label,
                'weight_percent' => (float) $row->weight_percent,
                'sort_order' => $row->sort_order,
            ])->values()->all();
        }

        $fields = [];
        if ($form->relationLoaded('fields')) {
            $fields = $form->fields->map(fn (EvaluationFormField $row) => [
                'id' => $row->id,
                'field_key' => $row->field_key,
                'label' => $row->label,
                'field_type' => $row->field_type,
                'is_enabled' => (bool) $row->is_enabled,
                'sort_order' => $row->sort_order,
            ])->values()->all();
        }

        $criteria = [];
        if ($form->relationLoaded('criteria')) {
            $criteria = $form->criteria->map(function (EvaluationFormCriterion $row) {
                $criterion = $row->relationLoaded('criterion') ? $row->criterion : null;

                return [
                    'id' => $row->id,
                    'criterion_id' => $row->criterion_id,
                    'name' => $row->name,
                    'weight' => (float) $row->weight,
                    'required_score_label' => $row->required_score_label,
                    'evaluator_mode' => $row->evaluator_mode,
                    'evaluator_role_keys' => $row->evaluator_role_keys ?? [],
                    'sort_order' => $row->sort_order,
                    'score_levels' => $criterion?->normalizedScoreLevels() ?? [],
                    'criteria_code' => $criterion?->criteria_code,
                ];
            })->values()->all();
        }

        $assignees = [];
        if ($form->relationLoaded('assignees')) {
            $assignees = $form->assignees->map(fn (EvaluationFormAssignee $row) => [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
                'employee_code' => $row->employee_code,
                'employee_name' => $row->employee_name,
                'department_code' => $row->department_code,
                'department_name' => $row->department_name,
                'dept_head_employee_id' => $row->dept_head_employee_id,
                'direct_manager_employee_id' => $row->direct_manager_employee_id,
                'board_employee_id' => $row->board_employee_id,
                'sort_order' => $row->sort_order,
            ])->values()->all();
        }

        $creator = $form->relationLoaded('creator') ? $form->creator : null;
        $manager = $form->relationLoaded('manager') ? $form->manager : null;
        $template = $form->relationLoaded('template') ? $form->template : null;
        $type = $form->relationLoaded('type') ? $form->type : null;

        $creatorAvatar = null;
        if ($creator && $creator->relationLoaded('employee')) {
            $creatorAvatar = PublicMediaUrl::fromPublicDisk($creator->employee?->avatar_path);
        }

        return [
            'id' => $form->id,
            'form_code' => $form->form_code,
            'name' => $form->name,
            'template_id' => $form->template_id,
            'template_name' => $template?->name,
            'template_code' => $template?->template_code,
            'type_id' => $form->type_id,
            'type_name' => $type?->name,
            'period_kind' => $periodKind?->value,
            'period_kind_label' => $periodKind?->label(),
            'period_month' => $form->period_month,
            'period_year' => $form->period_year,
            'period_start' => optional($form->period_start)?->format('Y-m-d'),
            'period_end' => optional($form->period_end)?->format('Y-m-d'),
            'period_label' => $this->periodLabel($form, $periodKind),
            'auto_create_next' => (bool) $form->auto_create_next,
            'manager_employee_id' => $form->manager_employee_id,
            'manager_name' => $manager?->full_name,
            'deadline' => optional($form->deadline)?->format('Y-m-d'),
            'evaluation_order' => $order?->value,
            'evaluation_order_label' => $order?->label(),
            'use_weight' => (bool) $form->use_weight,
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'created_by' => $form->created_by,
            'creator_name' => $creator?->display_name,
            'creator_avatar' => $creatorAvatar,
            'created_at' => optional($form->created_at)?->toIso8601String(),
            'updated_at' => optional($form->updated_at)?->toIso8601String(),
            'criteria_count' => $form->criteria_count ?? ($form->relationLoaded('criteria') ? $form->criteria->count() : null),
            'assignees_count' => $form->assignees_count ?? ($form->relationLoaded('assignees') ? $form->assignees->count() : null),
            'watcher_ids' => $watcherIds,
            'watchers' => $watchers,
            'raters' => $raters,
            'fields' => $fields,
            'criteria' => $criteria,
            'assignees' => $assignees,
        ];
    }

    private function periodLabel(EvaluationForm $form, ?EvaluationFormPeriodKind $kind): string
    {
        if (! $kind) {
            return '';
        }

        return match ($kind) {
            EvaluationFormPeriodKind::Month => sprintf(
                'Tháng %02d/%d',
                (int) ($form->period_month ?? 0),
                (int) ($form->period_year ?? 0),
            ),
            EvaluationFormPeriodKind::Quarter => sprintf(
                'Quý %d/%d',
                (int) ceil(((int) ($form->period_month ?? 1)) / 3),
                (int) ($form->period_year ?? 0),
            ),
            EvaluationFormPeriodKind::HalfYear => sprintf(
                'Nửa năm %d/%d',
                ((int) ($form->period_month ?? 1)) <= 6 ? 1 : 2,
                (int) ($form->period_year ?? 0),
            ),
            EvaluationFormPeriodKind::Year => sprintf('Năm %d', (int) ($form->period_year ?? 0)),
            EvaluationFormPeriodKind::Random,
            EvaluationFormPeriodKind::DateRange => sprintf(
                '%s – %s',
                optional($form->period_start)?->format('d/m/Y') ?? '',
                optional($form->period_end)?->format('d/m/Y') ?? '',
            ),
        };
    }
}
