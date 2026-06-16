<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Resources\CredentialAuditResource;
use App\Http\Resources\CredentialListResource;
use App\Http\Resources\CredentialResource;
use App\Models\Credential;
use App\Models\CredentialAccessRequest;
use App\Models\Department;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Credential\CredentialSummaryBuilder;
use App\Support\Enums\CredentialAccessRequestStatus;
use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialEnvironment;
use App\Support\Enums\CredentialPermission;
use App\Support\Enums\CredentialRelationType;
use App\Support\Enums\CredentialStatus;
use App\Support\Enums\CredentialType;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CredentialPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Credential::class);

        $account = $request->user();
        $query = Credential::query()
            ->visibleTo($account)
            ->with(['project', 'department', 'owner'])
            ->latest();

        $this->applyFilters($query, $request);

        $perPage = min(max((int) $request->query('per_page', 20), 5), 50);

        return Inertia::render('Credential/Index', [
            'credentials' => CredentialListResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) $request->only([
                'q', 'status', 'credential_type', 'system_category', 'project_id', 'department_id',
                'owner_id', 'provider_name', 'environment', 'is_shared', 'kpi', 'per_page',
            ]),
            'summary' => CredentialSummaryBuilder::build($account),
            'options' => $this->options(),
            'can' => $this->pageCan($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Credential::class);

        return Inertia::render('Credential/Create', [
            'options' => $this->options(),
            'defaults' => [
                'environment' => CredentialEnvironment::Production->value,
                'status' => CredentialStatus::Active->value,
                'owner_id' => $request->user()->id,
            ],
        ]);
    }

    public function edit(Request $request, Credential $credential): Response
    {
        $this->authorize('update', $credential);

        $credential->load(['project', 'department', 'owner']);

        return Inertia::render('Credential/Edit', [
            'credential' => (new CredentialResource($credential))->resolve(),
            'options' => $this->options(),
        ]);
    }

    public function show(Request $request, Credential $credential): Response
    {
        $this->authorize('view', $credential);

        $credential->load([
            'project',
            'department',
            'owner',
            'accessGrants.account',
            'outgoingRelations.target',
            'passwordHistories.changedBy',
        ]);

        return Inertia::render('Credential/Show', [
            'credential' => (new CredentialResource($credential))->resolve(),
            'options' => $this->options(),
            'linkable_credentials' => Credential::query()
                ->visibleTo($request->user())
                ->where('id', '!=', $credential->id)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'system_category'])
                ->map(fn (Credential $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'system_category' => [
                        'value' => $c->system_category->value,
                        'label' => $c->system_category->labelVi(),
                    ],
                ]),
            'pending_access_requests' => $request->user()->can('manageAccess', $credential)
                ? $credential->accessRequests()
                    ->where('status', CredentialAccessRequestStatus::Pending)
                    ->with('requester:id,display_name')
                    ->latest()
                    ->get()
                    ->map(fn (CredentialAccessRequest $r) => [
                        'id' => $r->id,
                        'requester' => $r->requester?->display_name,
                        'requested_permissions' => $r->requested_permissions,
                        'reason' => $r->reason,
                        'created_at' => $r->created_at?->toIso8601String(),
                    ])
                : [],
            'audit_logs' => CredentialAuditResource::collection(
                $credential->auditLogs()
                    ->with('account')
                    ->latest('created_at')
                    ->limit(50)
                    ->get(),
            ),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->query('q')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('provider_name', 'like', "%{$search}%")
                    ->orWhere('login_url', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('credential_type')) {
            $query->where('credential_type', $type);
        }

        if ($category = $request->query('system_category')) {
            $query->where('system_category', $category);
        }

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', (int) $departmentId);
        }

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', (int) $ownerId);
        }

        if ($provider = $request->query('provider_name')) {
            $query->where('provider_name', 'like', "%{$provider}%");
        }

        if ($env = $request->query('environment')) {
            $query->where('environment', $env);
        }

        if ($request->query('is_shared') === '1') {
            $query->where('is_shared', true);
        } elseif ($request->query('is_shared') === '0') {
            $query->where('is_shared', false);
        }

        $kpi = $request->query('kpi');
        $expiringCutoff = now()->addDays(30);
        match ($kpi) {
            'active' => $query->where('status', CredentialStatus::Active->value),
            'expiring_soon' => $query->where('status', CredentialStatus::Active->value)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), $expiringCutoff]),
            'locked' => $query->where('status', CredentialStatus::Locked->value),
            'shared' => $query->where('is_shared', true),
            'personal' => $query->where('is_shared', false),
            'no_owner' => $query->whereNull('owner_id'),
            'domain_expiring' => $query->where('system_category', CredentialCategory::Domain->value)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), $expiringCutoff]),
            'ssl_expiring' => $query->where('system_category', CredentialCategory::Ssl->value)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), $expiringCutoff]),
            'vps' => $query->where('system_category', CredentialCategory::Vps->value),
            'database' => $query->where('system_category', CredentialCategory::Database->value),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'credential_type' => CredentialType::options(),
            'system_category' => CredentialCategory::options(),
            'status' => CredentialStatus::options(),
            'environment' => CredentialEnvironment::options(),
            'permissions' => CredentialPermission::options(),
            'relation_types' => CredentialRelationType::options(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'owners' => SystemAccount::query()
                ->where('is_active', true)
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'username']),
            'employees' => Options::employees(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function pageCan(Request $request): array
    {
        return [
            'create' => $request->user()->can('create', Credential::class),
        ];
    }
}
