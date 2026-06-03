<?php

namespace App\Mail;

use App\Models\AiAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiAccountExpiryReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AiAccount $account,
        public int $daysLeft,
        public string $costLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Tài khoản AI sắp hết hạn — '.$this->account->tool_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ai-account-expiry-reminder',
        );
    }
}
