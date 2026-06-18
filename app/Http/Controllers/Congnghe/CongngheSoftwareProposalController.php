<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Congnghe\StoreCongngheSoftwareProposalRequest;
use App\Http\Resources\CongngheSoftwareProposalResource;
use App\Mail\CongngheSoftwareProposalMail;
use App\Models\CongngheSoftwareProposal;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Congnghe\CongngheSoftwareProposalRecorder;
use App\Support\Congnghe\CongngheContentRepository;
use App\Support\Enums\CongngheSoftwareProposalStatus;
use App\Support\Options;
use App\Support\SecurityAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CongngheSoftwareProposalController extends Controller
{
    public function __construct(
        private readonly CongngheSoftwareProposalRecorder $recorder,
        private readonly CongngheContentRepository $content,
    ) {}

    public function index(Request $request): Response
    {
        $account = $request->user();
        $baseQuery = $this->ownedProposalsQuery($account);
        $query = (clone $baseQuery)->withCount('attachments')->with('attachments')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($department = trim((string) $request->query('department', ''))) {
            $query->where('department', $department);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($request->query('email_sent') === '1') {
            $query->whereNotNull('email_sent_at');
        } elseif ($request->query('email_sent') === '0') {
            $query->whereNull('email_sent_at');
        }

        if ($request->query('acknowledged') === '1') {
            $query->where('status', '!=', CongngheSoftwareProposalStatus::New);
        } elseif ($request->query('acknowledged') === '0') {
            $query->where('status', CongngheSoftwareProposalStatus::New);
        }

        if ($request->query('has_attachments') === '1') {
            $query->has('attachments');
        } elseif ($request->query('has_attachments') === '0') {
            $query->doesntHave('attachments');
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('submitter_name', 'like', "%{$search}%")
                    ->orWhere('submitter_email', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 5), 30);

        $proposals = $query->paginate($perPage)->withQueryString();

        $summaryQuery = clone $baseQuery;

        return Inertia::render('Congnghe/MyProposals', [
            'chrome' => $this->content->portalChrome(),
            'proposals' => CongngheSoftwareProposalResource::collection($proposals),
            'filters' => (object) $request->only([
                'status',
                'q',
                'per_page',
                'department',
                'from',
                'to',
                'email_sent',
                'acknowledged',
                'has_attachments',
            ]),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'new' => (clone $summaryQuery)->where('status', CongngheSoftwareProposalStatus::New)->count(),
                'triaged' => (clone $summaryQuery)->where('status', CongngheSoftwareProposalStatus::Triaged)->count(),
                'in_progress' => (clone $summaryQuery)->where('status', CongngheSoftwareProposalStatus::InProgress)->count(),
                'done' => (clone $summaryQuery)->where('status', CongngheSoftwareProposalStatus::Done)->count(),
            ],
            'options' => [
                'statuses' => CongngheSoftwareProposalStatus::options(),
                'departments' => (clone $baseQuery)
                    ->select('department')
                    ->distinct()
                    ->orderBy('department')
                    ->pluck('department')
                    ->filter(fn (?string $name): bool => trim((string) $name) !== '')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(CongngheSoftwareProposal $proposal): Response
    {
        $this->authorize('viewAsSubmitter', $proposal);

        $proposal->load('attachments');

        return Inertia::render('Congnghe/MyProposalShow', [
            'chrome' => $this->content->portalChrome(),
            'proposal' => (new CongngheSoftwareProposalResource($proposal))->resolve(),
        ]);
    }

    public function create(): Response
    {
        $account = request()->user()?->loadMissing([
            'employee.orgMemberships.team',
        ]);
        $employee = $account?->employee;

        [$departmentName, $departmentId] = $this->resolveProposalDepartment($employee);

        $submitterEmail = trim((string) ($employee?->email ?? ''));
        if ($submitterEmail === '' && $account !== null) {
            $username = trim((string) $account->username);
            if ($username !== '' && str_contains($username, '@')) {
                $submitterEmail = $username;
            }
        }

        return Inertia::render('Congnghe/Proposal', [
            'chrome' => $this->content->portalChrome(),
            'defaults' => [
                'name' => $employee?->full_name ?? $account?->display_name ?? '',
                'email' => $submitterEmail,
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

        $department = Department::active()->findOrFail((int) $validated['department_id']);

        $proposal = $this->recorder->record(
            (int) $request->user()->id,
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department' => $department->name,
                'title' => $validated['title'],
                'content' => $validated['content'],
            ],
            $files,
        );

        SecurityAuditLogger::congngheProposal($request->user(), 'created', $proposal->id, [
            'reference_code' => $proposal->reference_code,
            'title' => $proposal->title,
        ]);

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

        return ['', null];
    }

    /**
     * @return Builder<CongngheSoftwareProposal>
     */
    private function ownedProposalsQuery(SystemAccount $account): Builder
    {
        $employeeEmail = strtolower(trim((string) ($account->employee?->email ?? '')));

        return CongngheSoftwareProposal::query()->where(function (Builder $query) use ($account, $employeeEmail): void {
            $query->where('system_account_id', $account->id);
            if ($employeeEmail !== '') {
                $query->orWhereRaw('LOWER(submitter_email) = ?', [$employeeEmail]);
            }
        });
    }
}
