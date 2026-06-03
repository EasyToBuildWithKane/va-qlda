<?php

namespace App\Models;

use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPurchaseProposal extends Model
{
    use HasUuids;

    protected $fillable = [
        'tool_name',
        'group_function',
        'license_type',
        'cost_amount',
        'cost_unit',
        'seats',
        'justification',
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
