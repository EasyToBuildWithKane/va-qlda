<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contract_id
 * @property Carbon|null $used_date
 * @property string|null $quantity
 * @property string|null $unit_price
 * @property string|null $init_fee
 * @property string|null $maintenance_fee
 * @property int|null $term_months
 * @property string|null $total
 * @property string|null $renewal_cost
 * @property string|null $note
 */
class ContractFinance extends Model
{
    protected $fillable = [
        'contract_id',
        'used_date',
        'quantity',
        'unit_price',
        'init_fee',
        'maintenance_fee',
        'term_months',
        'total',
        'renewal_cost',
        'note',
    ];

    protected $casts = [
        'used_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'init_fee' => 'decimal:2',
        'maintenance_fee' => 'decimal:2',
        'term_months' => 'integer',
        'total' => 'decimal:2',
        'renewal_cost' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
