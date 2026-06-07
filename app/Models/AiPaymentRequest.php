<?php

namespace App\Models;

use App\Support\Enums\AiPaymentRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPaymentRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'ai_purchase_proposal_id',
        'payment_request_code',
        'amount',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'paid_at',
        'payment_document_paths',
    ];

    protected $casts = [
        'status' => AiPaymentRequestStatus::class,
        'amount' => 'integer',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_document_paths' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pr) {
            if (empty($pr->payment_request_code)) {
                $pr->payment_request_code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $prefix = 'DNTT-'.now()->format('Ymd');
        $last = self::where('payment_request_code', 'like', $prefix.'-%')
            ->orderByDesc('payment_request_code')
            ->value('payment_request_code');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(AiPurchaseProposal::class, 'ai_purchase_proposal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'reviewed_by');
    }
}
