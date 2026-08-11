<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLoginMethod;
use App\Support\Enums\AiAccountPermission;
use App\Support\Enums\AiAccountPurchaseType;
use App\Support\Enums\AiAccountStatus;
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
                'view_password' => $request->user()->isAdminTier()
                    || $request->user()->allows('ai_account.view_password'),
                'trigger_reminder' => $request->user()->can('triggerReminder', AiAccount::class),
            ],
            'formHints' => [
                'notify' => config('ai_accounts.defaults.notify_hint'),
                'billing_monthly' => config('ai_accounts.defaults.billing_hint_monthly'),
                'billing_yearly' => config('ai_accounts.defaults.billing_hint_yearly'),
            ],
            'reminderSchedule' => config('ai_accounts.reminder.schedule_times', ['08:00', '14:00']),
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'status' => AiAccountStatus::options(),
                'usage_status' => AiAccountStatus::usageOptions(),
                'login_method' => AiAccountLoginMethod::options(),
                'purchase_type' => AiAccountPurchaseType::options(),
                'access_permissions' => AiAccountPermission::options(),
            ],
            'accessAccountOptions' => SystemAccount::query()
                ->where('is_active', true)
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'username']),
            'exchange_rate' => (int) config('ai_accounts.exchange_rate', 25_500),
        ]);
    }

    public function costReport(Request $request): Response
    {
        $this->authorize('viewAny', AiAccount::class);

        return Inertia::render('AiAccount/CostReport', [
            'can' => [
                'create' => $request->user()->can('create', AiAccount::class),
            ],
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'status' => AiAccountStatus::options(),
            ],
            'exchange_rate' => (int) config('ai_accounts.exchange_rate', 25_500),
        ]);
    }
}
