<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Congnghe\StoreCongngheSoftwareProposalRequest;
use App\Mail\CongngheSoftwareProposalMail;
use App\Models\Employee;
use App\Services\Congnghe\CongngheSoftwareProposalRecorder;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CongngheSoftwareProposalController extends Controller
{
    public function __construct(
        private readonly CongngheSoftwareProposalRecorder $recorder,
    ) {}

    public function create(): Response
    {
        $account = request()->user()?->loadMissing([
            'employee.orgMemberships.team',
        ]);
        $employee = $account?->employee;

        [$departmentName, $departmentId] = $this->resolveProposalDepartment($employee);

        return Inertia::render('Congnghe/Proposal', [
            'defaults' => [
                'name' => $employee?->full_name ?? $account?->display_name ?? '',
                'email' => $employee?->email ?? '',
                'department' => $departmentName,
                'department_id' => $departmentId,
            ],
            'departmentOptions' => Options::departments()->values()->all(),
            'recipientEmail' => config('va.congnghe_proposal_email'),
        ]);
    }

    public function store(StoreCongngheSoftwareProposalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $files = $request->file('attachments', []);

        $proposal = $this->recorder->record(
            (int) $request->user()->id,
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department' => $validated['department'],
                'title' => $validated['title'],
                'content' => $validated['content'],
            ],
            $files,
        );

        $recipient = (string) config('va.congnghe_proposal_email');

        try {
            Mail::to($recipient)->send(new CongngheSoftwareProposalMail($proposal));
            $proposal->forceFill(['email_sent_at' => now(), 'email_error' => null])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
            $proposal->forceFill([
                'email_error' => mb_substr($e->getMessage(), 0, 500),
            ])->saveQuietly();

            return redirect()
                ->route('congnghe.proposal')
                ->with('error', 'Đề xuất đã được lưu ('.$proposal->reference_code.') nhưng email chưa gửi được. Phòng Công nghệ vẫn xem trong hệ thống hoặc liên hệ '.$recipient);
        }

        return redirect()
            ->route('congnghe.proposal')
            ->with('success', 'Đã ghi nhận đề xuất '.$proposal->reference_code.' và gửi email tới Phòng Công Nghệ.');
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolveProposalDepartment(?Employee $employee): array
    {
        if ($employee === null) {
            return ['', null];
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];
        $name = '';

        foreach (['department_name', 'unit_name'] as $key) {
            $candidate = trim((string) ($meta[$key] ?? ''));
            if ($candidate !== '') {
                $name = $candidate;
                break;
            }
        }

        if ($name === '' && $employee->relationLoaded('orgMemberships')) {
            $team = $employee->orgMemberships->first()?->team;
            $name = trim((string) ($team?->name ?? ''));
        }

        if ($name === '') {
            return ['', null];
        }

        $departments = Options::departments();
        $match = $departments->first(
            fn (array $d): bool => strcasecmp((string) $d['name'], $name) === 0,
        );

        if ($match === null) {
            $match = $departments->first(
                fn (array $d): bool => str_contains(mb_strtolower($name), mb_strtolower((string) $d['name']))
                    || str_contains(mb_strtolower((string) $d['name']), mb_strtolower($name)),
            );
        }

        if ($match !== null) {
            return [(string) $match['name'], (int) $match['id']];
        }

        return [$name, null];
    }
}
