<?php

namespace App\Models\Evaluation;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_id
 * @property int $employee_id
 * @property string|null $employee_code
 * @property string|null $employee_name
 * @property string|null $department_code
 * @property string|null $department_name
 * @property int $dept_head_employee_id
 * @property int $direct_manager_employee_id
 * @property int|null $board_employee_id
 * @property int $sort_order
 */
class EvaluationFormAssignee extends Model
{
    protected $table = 'evaluation_form_assignees';

    protected $fillable = [
        'form_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'department_code',
        'department_name',
        'dept_head_employee_id',
        'direct_manager_employee_id',
        'board_employee_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function deptHead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'dept_head_employee_id');
    }

    public function directManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'direct_manager_employee_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'board_employee_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EvaluationFormSubmission::class, 'assignee_id');
    }
}
