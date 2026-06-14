<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\TaskEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SprintTaskSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function __construct(
        public Project $project,
        public Sprint $sprint,
        public Employee $assignee,
        public Collection $tasks,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_SPRINT_SUMMARY);
        $vars = $this->templateVars();
        $subject = $template?->is_active
            ? $template->renderSubject($vars)
            : '[QLDA] Sprint '.$this->sprint->name.' — '.$this->project->name;

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::findByKey(EmailTemplate::KEY_SPRINT_SUMMARY);
        $vars = $this->templateVars();

        if ($template !== null && $template->is_active) {
            return new Content(
                htmlString: $template->renderBody($vars),
            );
        }

        return new Content(
            view: 'mail.sprint-task-summary',
            with: [
                'project' => $this->project,
                'sprint' => $this->sprint,
                'assignee' => $this->assignee,
                'tasks' => $this->tasks,
                'tasksTable' => app(TaskEmailService::class)->buildTasksTableHtml($this->tasks),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function templateVars(): array
    {
        $emailService = app(TaskEmailService::class);

        return [
            'assignee_name' => $this->assignee->full_name ?? $this->assignee->name,
            'project_name' => $this->project->name,
            'sprint_name' => $this->sprint->name,
            'tasks_table' => $emailService->buildTasksTableHtml($this->tasks),
            'task_count' => (string) $this->tasks->count(),
        ];
    }
}
