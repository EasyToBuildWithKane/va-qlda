<?php

namespace App\Models\Evaluation;

use App\Models\SystemAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $template_code
 * @property string $name
 * @property string|null $description
 * @property string|null $position_code
 * @property string|null $position_name
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $created_by
 */
class EvaluationTemplate extends Model
{
    use SoftDeletes;

    public const CODE_PREFIX = 'MDG';

    protected $table = 'evaluation_templates';

    protected $fillable = [
        'template_code',
        'name',
        'description',
        'position_code',
        'position_name',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function templateCriteria(): HasMany
    {
        return $this->hasMany(EvaluationTemplateCriterion::class, 'template_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Next template_code suggestion — MDG001, MDG002, …
     */
    public static function suggestNextCode(): string
    {
        $codes = static::query()
            ->withTrashed()
            ->pluck('template_code');

        $max = 0;
        $prefix = preg_quote(self::CODE_PREFIX, '/');

        foreach ($codes as $code) {
            $raw = (string) $code;
            if (preg_match('/^'.$prefix.'(\d+)$/i', $raw, $m)) {
                $max = max($max, (int) $m[1]);
            } elseif (preg_match('/^\d+$/', $raw)) {
                $max = max($max, (int) $raw);
            }
        }

        return self::CODE_PREFIX.sprintf('%03d', $max + 1);
    }
}
