<?php

namespace App\Models;

use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\AiPurchaseType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPurchaseProposal extends Model
{
    use HasUuids;

    protected $fillable = [
        'tool_name',
        'subject_about',
        'send_to',
        'proposer_name',
        'proposer_position',
        'proposer_department',
        'group_function',
        'license_type',
        'cost_amount',
        'cost_unit',
        'seats',
        'justification',
        'proposal_content',
        'objectives',
        'quantity',
        'staff_count',
        'recipient_name',
        'recipient_position',
        'recipient_email',
        'recipient_phone',
        'purchase_type',
        'registration_email',
        'planned_use_date',
        'status',
        'rejection_reason',
        'review_notes',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'group_function' => AiAccountGroupFunction::class,
        'cost_unit' => AiAccountCostUnit::class,
        'status' => AiPurchaseProposalStatus::class,
        'cost_amount' => 'integer',
        'seats' => 'integer',
        'quantity' => 'integer',
        'staff_count' => 'integer',
        'purchase_type' => AiPurchaseType::class,
        'planned_use_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'reviewed_by');
    }
}
