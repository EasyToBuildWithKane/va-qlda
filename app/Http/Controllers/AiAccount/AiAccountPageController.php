<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
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
                'trigger_reminder' => $request->user()->can('triggerReminder', AiAccount::class),
            ],
            'options' => [
                'group_function' => AiAccountGroupFunction::options(),
                'cost_unit' => AiAccountCostUnit::options(),
                'license_types' => config('ai_accounts.license_types', []),
            ],
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
        ]);
    }
}
