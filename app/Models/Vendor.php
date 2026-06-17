<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vendor $vendor) {
            if (! $vendor->code) {
                $seq = static::query()->count() + 1;
                $vendor->code = 'NCC-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
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
