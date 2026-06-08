<?php

namespace App\Models;

use App\Support\AiPurchaseProposalRegistrationEmails;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\AiPurchaseType;
use App\Support\Enums\ProposalType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiPurchaseProposal extends Model
{
    use HasUuids;

    protected $fillable = [
        'proposal_code',
        'proposal_type',
        'tool_name',
        'vendor_name',
        'vendor_website',
        'subject_about',
        'send_to',
        'proposer_name',
        'proposer_position',
        'proposer_department',
        'group_function',
        'license_type',
        'cost_amount',
        'actual_cost',
        'cost_unit',
        'seats',
        'justification',
        'proposal_content',
        'description',
        'reason_for_proposal',
        'expected_benefit',
        'objectives',
        'quantity',
        'staff_count',
        'users_list',
        'department_using',
        'recipient_name',
        'recipient_position',
        'recipient_email',
        'recipient_phone',
        'purchase_type',
        'registration_email',
        'registration_emails',
        'planned_use_date',
        'start_date',
        'end_date',
        'attachment_paths',
        'status',
        'rejection_reason',
        'review_notes',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'ai_account_id',
    ];

    protected $casts = [
        'group_function' => AiAccountGroupFunction::class,
        'cost_unit' => AiAccountCostUnit::class,
        'status' => AiPurchaseProposalStatus::class,
        'proposal_type' => ProposalType::class,
        'purchase_type' => AiPurchaseType::class,
        'cost_amount' => 'integer',
        'actual_cost' => 'integer',
        'seats' => 'integer',
        'quantity' => 'integer',
        'staff_count' => 'integer',
        'planned_use_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
        'users_list' => 'array',
        'registration_emails' => 'array',
        'attachment_paths' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $proposal) {
            if (empty($proposal->proposal_code)) {
                $proposal->proposal_code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $prefix = 'PDX-'.now()->format('Ymd');
        $last = self::where('proposal_code', 'like', $prefix.'-%')
            ->orderByDesc('proposal_code')
            ->value('proposal_code');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->end_date) {
            return false;
        }

        return $this->end_date->isFuture()
            && $this->end_date->diffInDays(now()) <= $days;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'reviewed_by');
    }

    public function aiAccount(): BelongsTo
    {
        return $this->belongsTo(AiAccount::class);
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(AiAccount::class, 'ai_purchase_proposal_id');
    }

    public function staffSlots(): int
    {
        $staff = (int) ($this->staff_count ?? 0);
        if ($staff > 0) {
            return $staff;
        }

        $qty = (int) ($this->quantity ?? 0);

        return max(1, $qty > 0 ? $qty : 1);
    }

    public function provisionedAccountsCount(): int
    {
        if ($this->relationLoaded('linkedAccounts')) {
            return $this->linkedAccounts->count();
        }

        $viaFk = (int) $this->linkedAccounts()->count();
        if ($viaFk > 0) {
            return $viaFk;
        }

        return $this->ai_account_id ? 1 : 0;
    }

    public function hasRemainingAccountSlots(): bool
    {
        return $this->provisionedAccountsCount() < $this->staffSlots();
    }

    /**
     * @return list<string>
     */
    public function registrationEmailsList(): array
    {
        $stored = is_array($this->registration_emails) ? $this->registration_emails : null;

        return AiPurchaseProposalRegistrationEmails::normalize(
            $stored,
            $this->staffSlots(),
            $this->registration_email,
        );
    }

    public function paymentRequest(): HasOne
    {
        return $this->hasOne(AiPaymentRequest::class, 'ai_purchase_proposal_id');
    }
}
