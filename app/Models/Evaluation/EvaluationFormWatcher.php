<?php

namespace App\Models\Evaluation;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property int $employee_id
 */
class EvaluationFormWatcher extends Model
{
    protected $table = 'evaluation_form_watchers';

    protected $fillable = [
        'form_id',
        'employee_id',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
