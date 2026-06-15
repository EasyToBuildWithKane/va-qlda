<?php

namespace App\Models;

use App\Support\Enums\CongngheSoftwareProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $reference_code
 * @property int|null $system_account_id
 * @property string $submitter_name
 * @property string $submitter_email
 * @property string $department
 * @property string $title
 * @property string $content
 * @property CongngheSoftwareProposalStatus $status
 * @property \Illuminate\Support\Carbon|null $email_sent_at
 * @property string|null $email_error
 */
class CongngheSoftwareProposal extends Model
{
    protected $fillable = [
        'reference_code',
        'system_account_id',
        'submitter_name',
        'submitter_email',
        'department',
        'title',
        'content',
        'status',
        'email_sent_at',
        'email_error',
    ];

    protected $casts = [
        'status' => CongngheSoftwareProposalStatus::class,
        'email_sent_at' => 'datetime',
    ];

    public function systemAccount(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CongngheSoftwareProposalAttachment::class);
    }

    public function assignReferenceCode(): void
    {
        if ($this->reference_code !== null) {
            return;
        }

        $this->forceFill([
            'reference_code' => 'CN-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT),
        ])->saveQuietly();
    }

    /**
     * @return array{name: string, email: string, department: string, title: string, content: string, submitted_at: string, reference_code: string|null}
     */
    public function toMailPayload(): array
    {
        return [
            'name' => $this->submitter_name,
            'email' => $this->submitter_email,
            'department' => $this->department,
            'title' => $this->title,
            'content' => $this->content,
            'submitted_at' => $this->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
            'reference_code' => $this->reference_code,
        ];
    }
}
