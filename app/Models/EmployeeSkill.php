<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One leveled skill on a person's matrix.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $name
 * @property string|null $category
 * @property int $level
 * @property float|null $years_experience
 * @property bool $is_certified
 */
class EmployeeSkill extends Model
{
    protected $fillable = [
        'employee_id',
        'name',
        'category',
        'level',
        'years_experience',
        'is_certified',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'years_experience' => 'decimal:1',
        'is_certified' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
