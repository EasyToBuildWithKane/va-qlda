<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OnboardingProgress;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;

/**
 * Onboarding state for the interactive tour guide.
 *
 * Tour *content* lives client-side (resources/js/modules/onboarding/tours);
 * this service only owns persistence (progress per account) and the cheap
 * "smart context" flags that decide which contextual hint to surface.
 */
class OnboardingService
{
    /**
     * Whitelist of tour keys the client may report progress against. Keep in
     * sync with resources/js/modules/onboarding/tours/index.js.
     */
    public const TOURS = [
        'system_overview',
        'role_admin',
        'role_pm',
        'role_lead',
        'role_member',
    ];

    /**
     * Smart-context flags are global (do any projects/sprints/… exist) so they
     * are cached app-wide and invalidated on create (see AppServiceProvider).
     * The TTL is a self-healing fallback in case an invalidation is ever missed.
     */
    private const CONTEXT_CACHE_KEY = 'onboarding.context.v1';

    private const CONTEXT_CACHE_TTL = 300; // seconds

    /**
     * Build the shared Inertia payload: per-tour progress + smart-context flags.
     *
     * @return array<string, mixed>
     */
    public function payload(SystemAccount $account): array
    {
        $progress = OnboardingProgress::query()
            ->where('system_account_id', $account->id)
            ->get()
            ->keyBy('tour_key');

        $tours = [];
        foreach (self::TOURS as $key) {
            $row = $progress->get($key);
            $tours[$key] = [
                'status' => $row->status ?? 'pending',
                'current_step' => $row->current_step ?? 0,
                'total_steps' => $row->total_steps ?? 0,
            ];
        }

        $completed = $progress->where('status', 'completed')->count();

        return [
            'role' => $account->role->value,
            'tours' => $tours,
            'completed_tours' => $completed,
            'total_tours' => count(self::TOURS),
            'context' => $this->context($account),
        ];
    }

    /**
     * Cheap existence checks → which "next step" hint to nudge. Cached app-wide
     * (these flags are not per-user) so the shared Inertia prop adds ~0 queries
     * on the hot path.
     *
     * @return array<string, bool>
     */
    public function context(SystemAccount $account): array
    {
        return Cache::remember(self::CONTEXT_CACHE_KEY, self::CONTEXT_CACHE_TTL, static fn (): array => [
            'hasProject' => Project::query()->exists(),
            'hasSprint' => Sprint::query()->exists(),
            'hasTask' => Task::query()->exists(),
            'hasTeamMembers' => Employee::query()->count() > 1,
        ]);
    }

    /** Drop the cached context flags (called when projects/sprints/… are created). */
    public static function forgetContext(): void
    {
        Cache::forget(self::CONTEXT_CACHE_KEY);
    }

    /**
     * Upsert progress for a tour. Marking a step also moves status forward.
     */
    public function recordStep(SystemAccount $account, string $tourKey, int $currentStep, int $totalSteps): OnboardingProgress
    {
        return OnboardingProgress::updateOrCreate(
            ['system_account_id' => $account->id, 'tour_key' => $tourKey],
            [
                'current_step' => $currentStep,
                'total_steps' => $totalSteps,
                'status' => 'in_progress',
            ],
        );
    }

    public function complete(SystemAccount $account, string $tourKey): void
    {
        OnboardingProgress::updateOrCreate(
            ['system_account_id' => $account->id, 'tour_key' => $tourKey],
            ['status' => 'completed', 'completed_at' => now()],
        );
    }

    public function skip(SystemAccount $account, string $tourKey): void
    {
        OnboardingProgress::updateOrCreate(
            ['system_account_id' => $account->id, 'tour_key' => $tourKey],
            ['status' => 'skipped'],
        );
    }

    /** "Xem lại từ đầu" — clear a tour so the user can replay it cleanly. */
    public function reset(SystemAccount $account, string $tourKey): void
    {
        OnboardingProgress::query()
            ->where('system_account_id', $account->id)
            ->where('tour_key', $tourKey)
            ->delete();
    }
}
