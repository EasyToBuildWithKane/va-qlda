<?php

namespace App\Mail;

use App\Models\CongngheSoftwareProposal;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class CongngheSoftwareProposalRejectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CongngheSoftwareProposal $proposal,
        public string $rejectionReason,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_CONGNGHE_PROPOSAL_REJECTED);
        $vars = $this->templateVars();

        if ($template?->is_active) {
            return new Envelope(subject: $template->renderSubject($vars));
        }

        $ref = $this->proposal->reference_code ? " ({$this->proposal->reference_code})" : '';

        return new Envelope(
            subject: '[VAS · Phòng Công Nghệ] Đề xuất bị từ chối: '.$this->proposal->title.$ref,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_CONGNGHE_PROPOSAL_REJECTED);
        $vars = $this->templateVars();

        if ($template !== null && $template->is_active) {
            return new Content(
                htmlString: $template->renderBodyForDelivery($vars),
            );
        }

        $payload = $this->proposal->toMailPayload();
        $payload['rejection_reason'] = $this->rejectionReason;
        $payload['status_label'] = $this->proposal->status->label();
        $payload['mine_url'] = url('/congnghe/de-xuat-cua-toi/'.$this->proposal->id);

        $inner = View::make('mail.congnghe-software-proposal-rejected', [
            'proposal' => $payload,
        ])->render();

        return new Content(
            htmlString: \App\Support\Mail\EmailBrandLayout::wrap(
                $inner,
                'Thông báo từ chối đề xuất — '.$payload['title'],
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    private function templateVars(): array
    {
        $payload = $this->proposal->toMailPayload();

        return [
            'submitter_name' => $payload['name'],
            'proposal_title' => $payload['title'],
            'reference_code' => $payload['reference_code'] ?? '',
            'department' => $payload['department'],
            'submitted_at' => $payload['submitted_at'],
            'rejection_reason' => $this->rejectionReason,
            'status_label' => $this->proposal->status->label(),
            'mine_url' => url('/congnghe/de-xuat-cua-toi/'.$this->proposal->id),
        ];
    }
}
