<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Models\Employee;
use App\Support\EmployeePickerMapper;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLifecycleStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\ProposalType;
use App\Support\Enums\SystemRole;
use App\Support\Options;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAccountPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        return Inertia::render('AiAccount/Index', [
            'can' => [
                'create' => $request->user()->can('create', AiAccount::class),
                'view_password' => $request->user()->role === SystemRole::Admin,
                'manage_password_viewers' => $request->user()->can('managePasswordViewers', AiAccount::class),
                'trigger_reminder' => $request->user()->can('triggerReminder', AiAccount::class),
            ],
            'form_hints' => [
                'notify' => config('ai_accounts.defaults.notify_hint'),
                'billing_monthly' => config('ai_accounts.defaults.billing_hint_monthly'),
                'billing_yearly' => config('ai_accounts.defaults.billing_hint_yearly'),
            ],
            'reminder_schedule' => config('ai_accounts.reminder.schedule_times', ['08:00', '14:00']),
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'license_types' => config('ai_accounts.license_types', []),
                'status' => AiAccountStatus::options(),
            ],
            'exchange_rate' => (int) config('ai_accounts.exchange_rate', 25_500),
        ]);
    }

    public function costByGroup(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        return Inertia::render('AiAccount/CostByGroup', [
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
            ],
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        return Inertia::render('AiAccount/Dashboard', [
            'exchange_rate' => (int) config('ai_accounts.exchange_rate', 25_500),
        ]);
    }

    public function analytics(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        return Inertia::render('AiAccount/AnalyticsReport', [
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'status' => AiAccountStatus::options(),
                'lifecycle_status' => AiAccountLifecycleStatus::options(),
                'proposal_status' => AiPurchaseProposalStatus::options(),
            ],
            'exporter' => [
                'name' => $request->user()->employee?->full_name ?? $request->user()->username,
            ],
        ]);
    }

    public function costReport(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        $user = $request->user();
        $user->loadMissing('employee');
        $employee = $user->employee;
        $dept = is_array($employee?->meta) ? ($employee->meta['department_name'] ?? null) : null;

        return Inertia::render('AiAccount/CostReport', [
            'can' => [
                'create' => $request->user()->can('create', AiAccount::class),
                'propose' => $request->user()->can('create', \App\Models\AiPurchaseProposal::class),
                'review_proposals' => $request->user()->role === SystemRole::Admin,
            ],
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'license_types' => config('ai_accounts.license_types', []),
                'proposal_type' => ProposalType::options(),
                'proposal_status' => AiPurchaseProposalStatus::options(),
                'purchase_type' => [
                    ['value' => 'new', 'label' => 'Mua mới'],
                    ['value' => 'renewal', 'label' => 'Gia hạn'],
                ],
            ],
            'proposal_defaults' => [
                'send_to' => config('ai_accounts.proposal.send_to_default'),
                'objectives' => config('ai_accounts.proposal.objectives_sample'),
                'proposer_name' => $employee?->full_name ?? $user->username,
                'proposer_position' => $employee?->role_title,
                'proposer_department' => $dept,
                'recipient_email' => $employee?->email,
                'recipient_phone' => $employee?->phone,
                'proposer_employee_id' => $employee?->id,
            ],
            'form_lookups' => $this->proposalFormLookups($employee),
        ]);
    }

    /** @return array<string, mixed> */
    private function proposalFormLookups(?Employee $currentEmployee = null): array
    {
        $tools = AiAccount::query()
            ->distinct()
            ->orderBy('tool_name')
            ->pluck('tool_name')
            ->merge(
                AiPurchaseProposal::query()
                    ->distinct()
                    ->orderBy('tool_name')
                    ->pluck('tool_name')
            )
            ->unique()
            ->filter()
            ->values()
            ->all();

        $vendors = AiPurchaseProposal::query()
            ->whereNotNull('vendor_name')
            ->where('vendor_name', '!=', '')
            ->distinct()
            ->orderBy('vendor_name')
            ->pluck('vendor_name')
            ->values()
            ->all();

        $sendTo = collect([config('ai_accounts.proposal.send_to_default')])
            ->merge(
                AiPurchaseProposal::query()
                    ->whereNotNull('send_to')
                    ->where('send_to', '!=', '')
                    ->distinct()
                    ->pluck('send_to')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $employees = EmployeePickerMapper::search(null, 50);

        $accountTemplates = AiAccount::query()
            ->orderBy('tool_name')
            ->get(['tool_name', 'license_type', 'group_function', 'cost_amount', 'cost_unit', 'email_registered'])
            ->map(fn (AiAccount $a) => [
                'tool_name' => $a->tool_name,
                'license_type' => $a->license_type,
                'group_function' => $a->group_function->value,
                'cost_amount' => $a->cost_amount,
                'cost_unit' => $a->cost_unit->value,
                'registration_email' => $a->email_registered,
            ])
            ->values()
            ->all();

        return [
            'employees' => $employees,
            'departments' => Options::departments()->values()->all(),
            'tools' => $tools,
            'vendors' => $vendors,
            'send_to' => $sendTo,
            'account_templates' => $accountTemplates,
        ];
    }
}
