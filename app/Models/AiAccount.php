<?php

namespace App\Models;

use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $tool_name
 * @property AiAccountGroupFunction $group_function
 * @property string $email_registered
 * @property string|null $login_password
 * @property \Illuminate\Support\Carbon $purchase_date
 * @property \Illuminate\Support\Carbon $expiry_date
 * @property \Illuminate\Support\Carbon|null $proposal_sent_at
 * @property \Illuminate\Support\Carbon|null $payment_request_sent_at
 * @property array<int, array<string, mixed>>|null $proposal_document_paths
 * @property array<int, array<string, mixed>>|null $payment_request_document_paths
 * @property int $cost_amount
 * @property AiAccountCostUnit $cost_unit
 * @property AiAccountStatus $status
 * @property int $notify_before_days
 * @property \Illuminate\Support\Carbon|null $last_reminded_at
 * @property string|null $notes
 */
class AiAccount extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tool_name',
        'group_function',
        'email_registered',
        'login_password',
        'purchase_date',
        'expiry_date',
        'proposal_sent_at',
        'payment_request_sent_at',
        'proposal_document_paths',
        'payment_request_document_paths',
        'cost_amount',
        'cost_unit',
        'status',
        'notify_before_days',
        'last_reminded_at',
        'notes',
    ];

    protected $casts = [
        'group_function' => AiAccountGroupFunction::class,
        'cost_unit' => AiAccountCostUnit::class,
        'status' => AiAccountStatus::class,
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'proposal_sent_at' => 'date',
        'payment_request_sent_at' => 'date',
        'proposal_document_paths' => 'array',
        'payment_request_document_paths' => 'array',
        'cost_amount' => 'integer',
        'notify_before_days' => 'integer',
        'last_reminded_at' => 'datetime',
        'login_password' => 'encrypted',
    ];

    /**
     * @param  'proposal'|'payment_request'  $kind
     * @return list<array{path:string,original_name:string,mime_type:?string,size:?int,url:?string}>
     */
    public function documentsFor(string $kind): array
    {
        $raw = $kind === 'payment_request'
            ? ($this->payment_request_document_paths ?? [])
            : ($this->proposal_document_paths ?? []);

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_values($raw) as $index => $doc) {
            if (! is_array($doc) || empty($doc['path']) || ! is_string($doc['path'])) {
                continue;
            }
            $path = $doc['path'];
            $exists = Storage::disk('public')->exists($path);
            $out[] = [
                'path' => $path,
                'original_name' => is_string($doc['original_name'] ?? null) ? $doc['original_name'] : basename($path),
                'mime_type' => is_string($doc['mime_type'] ?? null) ? $doc['mime_type'] : null,
                'size' => isset($doc['size']) && is_numeric($doc['size']) ? (int) $doc['size'] : null,
                'url' => $exists
                    ? route('api.ai-accounts.documents.file', [
                        'aiAccount' => $this->id,
                        'kind' => $kind === 'payment_request' ? 'payment-request' : 'proposal',
                        'index' => $index,
                    ])
                    : null,
            ];
        }

        return $out;
    }
}
