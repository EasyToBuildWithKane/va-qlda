<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $vendor_id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 */
class ContractCategory extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContractCategory $category) {
            if (! $category->slug) {
                $category->slug = Str::slug($category->name) ?: 'nhom';
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** NCC gắn loại dịch vụ này (nhiều-nhiều). */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(
            Vendor::class,
            'vendor_service_categories',
            'contract_category_id',
            'vendor_id',
        )->withTimestamps();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'category_id');
    }
}
