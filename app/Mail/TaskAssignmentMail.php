<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignmentMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Task $task,
        public Employee $assignee,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_TASK_ASSIGNED);
        $vars = $this->templateVars();
        $subject = $template?->is_active
            ? $template->renderSubject($vars)
            : '[Workspace] Giao việc: '.$this->task->title;

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_TASK_ASSIGNED);
        $vars = $this->templateVars();

        if ($template !== null && $template->is_active) {
            return new Content(
                htmlString: $template->renderBodyForDelivery($vars),
            );
        }

        return new Content(
            view: 'mail.task-assignment',
            with: [
                'task' => $this->task,
                'assignee' => $this->assignee,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function templateVars(): array
    {
        $project = $this->task->project;

        return [
            'assignee_name' => $this->assignee->full_name ?? $this->assignee->name,
            'task_name' => $this->task->title,
            'project_name' => $project?->name ?? '',
            'sprint_name' => $this->task->sprint?->name ?? '—',
            'due_date' => $this->task->due_date?->format('d/m/Y') ?? '—',
            'task_url' => url("/projects/{$this->task->project_id}?tab=sprints&task={$this->task->id}"),
        ];
    }
}
