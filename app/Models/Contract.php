<?php

namespace App\Models;

use App\Support\Enums\ContractBillingCycle;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $root_contract_id
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
        'root_contract_id',
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
                $contract->code = static::previewNextCode();
            }
        });
    }

    /** Mã dự kiến khi tạo mới (hiển thị form — khớp logic `creating`). */
    public static function previewNextCode(): string
    {
        $seq = static::query()->withTrashed()->count() + 1;

        return 'HD-'.now()->format('y').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
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

    /** Hợp đồng gốc của bộ (null nếu chính nó là gốc). */
    public function rootContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'root_contract_id');
    }

    /** Các bản gia hạn / phụ lục trỏ về hợp đồng này. */
    public function addenda(): HasMany
    {
        return $this->hasMany(Contract::class, 'root_contract_id');
    }

    /**
     * Chi phí năm = tổng tiền hợp đồng (sum `total` trên contract_finances).
     * Fallback phí duy trì × 12, cột `annual_cost` / `lifecycle_cost`, rồi `monthly_cost × 12`.
     */
    public function financeTotalSum(): float
    {
        if (! $this->relationLoaded('finances') || $this->finances->isEmpty()) {
            return 0.0;
        }

        return (float) $this->finances->sum(fn (ContractFinance $f) => (float) ($f->total ?? 0));
    }

    public function annualCostResolved(): float
    {
        if ($this->relationLoaded('finances') && $this->finances->isNotEmpty()) {
            $total = $this->financeTotalSum();
            if ($total > 0) {
                return round($total, 2);
            }
            $maintenance = (float) $this->finances->sum(fn (ContractFinance $f) => (float) ($f->maintenance_fee ?? 0));
            if ($maintenance > 0) {
                return round($maintenance * 12, 2);
            }
        }

        if ($this->lifecycle_cost !== null && (float) $this->lifecycle_cost > 0) {
            return round((float) $this->lifecycle_cost, 2);
        }

        if ($this->annual_cost !== null) {
            return round((float) $this->annual_cost, 2);
        }

        return round((float) ($this->monthly_cost ?? 0) * 12, 2);
    }

    public function monthlyCostResolved(): ?float
    {
        if ($this->relationLoaded('finances') && $this->finances->isNotEmpty()) {
            $monthly = (float) $this->finances->sum(fn (ContractFinance $f) => (float) ($f->maintenance_fee ?? 0));
            if ($monthly > 0) {
                return round($monthly, 2);
            }
        }

        return $this->monthly_cost !== null ? round((float) $this->monthly_cost, 2) : null;
    }

    public function unitPriceResolved(): ?float
    {
        if ($this->relationLoaded('finances') && $this->finances->isNotEmpty()) {
            $latest = $this->finances->first();
            if ($latest?->unit_price !== null) {
                return round((float) $latest->unit_price, 2);
            }
        }

        return $this->unit_price !== null ? round((float) $this->unit_price, 2) : null;
    }

    public function lifecycleCostResolved(): ?float
    {
        if ($this->relationLoaded('finances') && $this->finances->isNotEmpty()) {
            $total = $this->financeTotalSum();
            if ($total > 0) {
                return round($total, 2);
            }
        }

        return $this->lifecycle_cost !== null ? round((float) $this->lifecycle_cost, 2) : null;
    }

    /** Tổng phí khởi tạo (triển khai mới) từ contract_finances. */
    public function initTotal(): float
    {
        if (! $this->relationLoaded('finances')) {
            return 0.0;
        }

        return round((float) $this->finances->sum(fn (ContractFinance $f) => (float) ($f->init_fee ?? 0)), 2);
    }
}
