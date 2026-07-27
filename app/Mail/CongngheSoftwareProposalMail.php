<?php

namespace App\Mail;

use App\Models\CongngheSoftwareProposal;
use App\Models\CongngheSoftwareProposalAttachment;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

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
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_CONGNGHE_PROPOSAL_SUBMITTED);
        $vars = $this->templateVars($payload);

        $subject = $template?->is_active
            ? $template->renderSubject($vars)
            : '[VAS · Phòng Công Nghệ] Đề xuất PM: '.$payload['title']
                .($payload['reference_code'] ? " ({$payload['reference_code']})" : '');

        return new Envelope(
            subject: $subject,
            replyTo: [
                new Address($payload['email'], $payload['name']),
            ],
        );
    }

    public function content(): Content
    {
        $payload = $this->proposal->toMailPayload();
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_CONGNGHE_PROPOSAL_SUBMITTED);
        $vars = $this->templateVars($payload);

        if ($template !== null && $template->is_active) {
            return new Content(
                htmlString: $template->renderBodyForDelivery($vars),
            );
        }

        $inner = View::make('mail.congnghe-software-proposal', [
            'proposal' => $payload,
        ])->render();

        return new Content(
            htmlString: \App\Support\Mail\EmailBrandLayout::wrap(
                $inner,
                'Đề xuất giải pháp phần mềm từ '.$payload['name'].' — '.$payload['title'],
            ),
        );
    }

    /**
     * @param  array{name: string, email: string, department: string, title: string, content: string, submitted_at: string, reference_code: string|null}  $payload
     * @return array<string, string>
     */
    private function templateVars(array $payload): array
    {
        return [
            'submitter_name' => $payload['name'],
            'submitter_email' => $payload['email'],
            'proposal_title' => $payload['title'],
            'proposal_content' => $payload['content'],
            'reference_code' => $payload['reference_code'] ?? '',
            'department' => $payload['department'],
            'submitted_at' => $payload['submitted_at'],
            'portal_url' => route('congnghe'),
        ];
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
