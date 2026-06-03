<?php

namespace App\Models;

use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tool_name
 * @property string $license_type
 * @property string|null $license_key
 * @property AiAccountGroupFunction $group_function
 * @property string $email_registered
 * @property string|null $login_password
 * @property \Illuminate\Support\Carbon $purchase_date
 * @property \Illuminate\Support\Carbon $expiry_date
 * @property int $cost_amount
 * @property AiAccountCostUnit $cost_unit
 * @property int|null $seats
 * @property AiAccountStatus $status
 * @property int $notify_before_days
 * @property \Illuminate\Support\Carbon|null $last_reminded_at
 * @property string|null $notes
 */
class AiAccount extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tool_name',
        'license_type',
        'license_key',
        'group_function',
        'email_registered',
        'login_password',
        'purchase_date',
        'expiry_date',
        'cost_amount',
        'cost_unit',
        'seats',
        'status',
        'notify_before_days',
        'last_reminded_at',
        'notes',
    ];

    protected $casts = [
        'group_function' => AiAccountGroupFunction::class,
        'cost_unit' => AiAccountCostUnit::class,
        'status' => AiAccountStatus::class,
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'cost_amount' => 'integer',
        'seats' => 'integer',
        'notify_before_days' => 'integer',
        'last_reminded_at' => 'datetime',
        'login_password' => 'encrypted',
    ];

    public function purchaseProposal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AiPurchaseProposal::class, 'ai_account_id');
    }
}
