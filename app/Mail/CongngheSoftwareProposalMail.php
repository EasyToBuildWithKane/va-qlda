<?php

namespace App\Mail;

use App\Models\CongngheSoftwareProposal;
use App\Models\CongngheSoftwareProposalAttachment;
use App\Support\Mail\EmailBrandLayout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CongngheSoftwareProposalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CongngheSoftwareProposal $proposal,
    ) {}

    public function envelope(): Envelope
    {
        $payload = $this->proposal->toMailPayload();
        $ref = $payload['reference_code'] ? " ({$payload['reference_code']})" : '';

        return new Envelope(
            subject: '[VAS · Phòng Công Nghệ] Đề xuất PM: '.$payload['title'].$ref,
            replyTo: [
                new Address($payload['email'], $payload['name']),
            ],
        );
    }

    public function content(): Content
    {
        $payload = $this->proposal->toMailPayload();

        $inner = view('mail.congnghe-software-proposal', [
            'proposal' => $payload,
        ])->render();

        return new Content(
            htmlString: EmailBrandLayout::wrap(
                $inner,
                'Đề xuất giải pháp phần mềm từ '.$payload['name'].' — '.$payload['title'],
            ),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $out = [];

        foreach ($this->proposal->attachments as $attachment) {
            if (! $attachment instanceof CongngheSoftwareProposalAttachment || ! $attachment->fileExists()) {
                continue;
            }

            $diskPath = Storage::disk('public')->path($attachment->path);

            $out[] = Attachment::fromPath($diskPath)
                ->as($attachment->original_name)
                ->withMime($attachment->mime_type ?? 'application/octet-stream');
        }

        return $out;
    }
}
