<?php

namespace App\Models;

use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLifecycleStatus;
use App\Support\Enums\AiAccountRenewalPaymentStatus;
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
 * @property AiAccountRenewalPaymentStatus $renewal_payment_status
 * @property \Illuminate\Support\Carbon|null $renewal_paid_at
 * @property \Illuminate\Support\Carbon|null $status_locked_at
 * @property int $notify_before_days
 * @property \Illuminate\Support\Carbon|null $last_reminded_at
 * @property \Illuminate\Support\Carbon|null $last_payment_reminded_at
 * @property string|null $notes
 * @property AiAccountLifecycleStatus $lifecycle_status
 * @property int|null $purchased_by
 * @property int|null $actual_purchase_cost
 * @property \Illuminate\Support\Carbon|null $allocated_at
 * @property string|null $allocated_to_name
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
        'renewal_payment_status',
        'renewal_paid_at',
        'status_locked_at',
        'notify_before_days',
        'last_reminded_at',
        'last_payment_reminded_at',
        'notes',
        'lifecycle_status',
        'purchased_by',
        'actual_purchase_cost',
        'allocated_at',
        'allocated_to_name',
    ];

    protected $casts = [
        'group_function' => AiAccountGroupFunction::class,
        'cost_unit' => AiAccountCostUnit::class,
        'status' => AiAccountStatus::class,
        'renewal_payment_status' => AiAccountRenewalPaymentStatus::class,
        'renewal_paid_at' => 'datetime',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'cost_amount' => 'integer',
        'seats' => 'integer',
        'notify_before_days' => 'integer',
        'last_reminded_at' => 'datetime',
        'last_payment_reminded_at' => 'datetime',
        'status_locked_at' => 'datetime',
        'login_password' => 'encrypted',
        'lifecycle_status' => AiAccountLifecycleStatus::class,
        'actual_purchase_cost' => 'integer',
        'allocated_at' => 'date',
    ];

    public function purchaseProposal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AiPurchaseProposal::class, 'ai_account_id');
    }

    public function passwordViewers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AiAccountPasswordViewer::class);
    }

    public function purchasedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'purchased_by');
    }
}
