<?php

namespace App\Models\Evaluation;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationFormSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_id
 * @property int $assignee_id
 * @property string $rater_role_key
 * @property int $rater_employee_id
 * @property string $status
 * @property float|null $total_score
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property int|null $submitted_by
 */
class EvaluationFormSubmission extends Model
{
    protected $table = 'evaluation_form_submissions';

    protected $fillable = [
        'form_id',
        'assignee_id',
        'rater_role_key',
        'rater_employee_id',
        'status',
        'total_score',
        'comment',
        'submitted_at',
        'submitted_by',
    ];

    protected $casts = [
        'total_score' => 'float',
        'submitted_at' => 'datetime',
        'status' => EvaluationFormSubmissionStatus::class,
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormAssignee::class, 'assignee_id');
    }

    public function raterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rater_employee_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'submitted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EvaluationFormScoreLine::class, 'submission_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(EvaluationFormFieldValue::class, 'submission_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === EvaluationFormSubmissionStatus::Submitted;
    }
}
