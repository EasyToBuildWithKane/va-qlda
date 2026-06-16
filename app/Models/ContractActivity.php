<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contract_id
 * @property int|null $employee_id
 * @property string $event
 * @property string $description
 * @property array<string, mixed>|null $meta
 */
class ContractActivity extends Model
{
    protected $fillable = [
        'contract_id',
        'employee_id',
        'event',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
