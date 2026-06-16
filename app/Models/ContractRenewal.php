<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contract_id
 * @property Carbon|null $previous_expiry
 * @property Carbon|null $new_expiry
 * @property string|null $previous_cost
 * @property string|null $new_cost
 * @property string|null $note
 * @property int|null $renewed_by_id
 */
class ContractRenewal extends Model
{
    protected $fillable = [
        'contract_id',
        'previous_expiry',
        'new_expiry',
        'previous_cost',
        'new_cost',
        'note',
        'renewed_by_id',
    ];

    protected $casts = [
        'previous_expiry' => 'date',
        'new_expiry' => 'date',
        'previous_cost' => 'decimal:2',
        'new_cost' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'renewed_by_id');
    }
}
