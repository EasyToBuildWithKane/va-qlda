<?php

namespace App\Models;

use App\Support\Enums\ContractBillingCycle;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $vendor_id
 * @property int|null $category_id
 * @property int|null $department_id
 * @property string|null $using_unit
 * @property int|null $owner_id
 * @property int|null $manager_id
 * @property string $currency
 * @property string|null $unit_price
 * @property string|null $monthly_cost
 * @property string|null $annual_cost
 * @property string|null $lifecycle_cost
 * @property ContractPaymentStatus $payment_status
 * @property ContractBillingCycle|null $billing_cycle
 * @property Carbon|null $signed_date
 * @property Carbon|null $effective_date
 * @property Carbon|null $expiry_date
 * @property bool $auto_renew
 * @property int|null $renewal_term_months
 * @property int|null $notice_period_days
 * @property ContractStatus $status
 * @property int|null $health_score
 */
class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'vendor_id',
        'category_id',
        'department_id',
        'using_unit',
        'owner_id',
        'manager_id',
        'currency',
        'unit_price',
        'monthly_cost',
        'annual_cost',
        'lifecycle_cost',
        'payment_status',
        'billing_cycle',
        'signed_date',
        'effective_date',
        'expiry_date',
        'auto_renew',
        'renewal_term_months',
        'notice_period_days',
        'status',
        'health_score',
    ];

    protected $casts = [
        'payment_status' => ContractPaymentStatus::class,
        'billing_cycle' => ContractBillingCycle::class,
        'status' => ContractStatus::class,
        'unit_price' => 'decimal:2',
        'monthly_cost' => 'decimal:2',
        'annual_cost' => 'decimal:2',
        'lifecycle_cost' => 'decimal:2',
        'signed_date' => 'date',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'auto_renew' => 'boolean',
        'renewal_term_months' => 'integer',
        'notice_period_days' => 'integer',
        'health_score' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            if (! $contract->code) {
                $seq = static::query()->withTrashed()->count() + 1;
                $contract->code = 'HD-'.now()->format('y').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /** Số ngày còn lại tới khi hết hạn (âm = đã quá hạn, null = chưa có ngày). */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return Carbon::today()->diffInDays($this->expiry_date, false);
    }

    /**
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ContractStatus::Active->value,
            ContractStatus::ExpiringSoon->value,
            ContractStatus::PendingRenewal->value,
        ]);
    }

    /**
     * Hợp đồng sắp hết hạn trong `$days` ngày (chưa quá hạn, còn sống).
     *
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotIn('status', [ContractStatus::Terminated->value])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractCategory::class, 'category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ContractActivity::class)->latest();
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(ContractRenewal::class)->latest();
    }

    public function finances(): HasMany
    {
        return $this->hasMany(ContractFinance::class)->orderByDesc('used_date');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ContractReview::class)->orderByDesc('reviewed_at');
    }

    /** Đánh giá NCC gần nhất (dùng cho badge & cảnh báo gia hạn). */
    public function latestReview(): HasOne
    {
        return $this->hasOne(ContractReview::class)->latestOfMany('reviewed_at');
    }
}
