<?php

namespace App\Models;

use App\Support\Enums\VendorCooperationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $tax_code
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $address
 * @property int|null $rating
 * @property string|null $notes
 * @property bool $is_active
 * @property \App\Support\Enums\VendorCooperationStatus $cooperation_status
 */
class Vendor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'tax_code',
        'contact_name',
        'email',
        'phone',
        'website',
        'address',
        'rating',
        'notes',
        'is_active',
        'cooperation_status',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
        'cooperation_status' => VendorCooperationStatus::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Vendor $vendor) {
            if ($vendor->cooperation_status instanceof VendorCooperationStatus) {
                $vendor->is_active = $vendor->cooperation_status->isActiveFlag();
            } elseif (is_string($vendor->cooperation_status) && $vendor->cooperation_status !== '') {
                $status = VendorCooperationStatus::tryFrom($vendor->cooperation_status);
                if ($status) {
                    $vendor->is_active = $status->isActiveFlag();
                }
            } elseif ($vendor->isDirty('is_active') && ! $vendor->isDirty('cooperation_status')) {
                // Legacy payload chỉ gửi is_active.
                $vendor->cooperation_status = $vendor->is_active
                    ? VendorCooperationStatus::Active
                    : VendorCooperationStatus::Inactive;
            }
        });

        static::creating(function (Vendor $vendor) {
            if (! $vendor->code) {
                $seq = static::query()->count() + 1;
                $vendor->code = 'NCC-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            }
            if (! $vendor->cooperation_status) {
                $vendor->cooperation_status = $vendor->is_active
                    ? VendorCooperationStatus::Active
                    : VendorCooperationStatus::Inactive;
            }
        });
    }

    /**
     * @param  Builder<Vendor>  $query
     * @return Builder<Vendor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ContractCategory::class)->orderBy('sort_order');
    }

    /**
     * Nhóm dịch vụ NCC cung cấp (nhiều loại) — pivot `vendor_service_categories`.
     * Khác `categories()` (HasMany qua vendor_id trên contract_categories, legacy Explorer).
     */
    public function serviceCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ContractCategory::class,
            'vendor_service_categories',
            'vendor_id',
            'contract_category_id',
        )->withTimestamps()->orderBy('sort_order')->orderBy('name');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VendorReview::class)->orderByDesc('reviewed_at');
    }

    /** Đánh giá NCC gần nhất (dùng cho badge & cảnh báo gia hạn). */
    public function latestReview(): HasOne
    {
        return $this->hasOne(VendorReview::class)->latestOfMany('reviewed_at');
    }
}
