<?php

namespace App\Models;

use App\Support\Enums\CertificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $name
 * @property string|null $provider
 * @property string|null $credential_id
 * @property string|null $credential_url
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class Certification extends Model
{
    protected $fillable = [
        'employee_id',
        'name',
        'provider',
        'credential_id',
        'credential_url',
        'issued_at',
        'expires_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Validity derived from the expiry date (null expiry = never expires). */
    public function status(): CertificationStatus
    {
        if ($this->expires_at === null) {
            return CertificationStatus::Valid;
        }

        $now = Carbon::today();

        return match (true) {
            $this->expires_at->lt($now) => CertificationStatus::Expired,
            $this->expires_at->lte($now->copy()->addDays(60)) => CertificationStatus::Expiring,
            default => CertificationStatus::Valid,
        };
    }
}
