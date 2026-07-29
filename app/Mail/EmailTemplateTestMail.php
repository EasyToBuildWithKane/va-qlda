<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailTemplateTestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $htmlBody,
    ) {}

    public function envelope(): Envelope
    {
        $fromName = (string) (config('task_email.from_name') ?: config('va.app_name', 'VAschools Workspace'));
        $fromAddress = (string) config('mail.from.address', 'hello@example.com');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: '[TEST] '.$this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }
}
