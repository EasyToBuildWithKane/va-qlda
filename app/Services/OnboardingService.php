<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OnboardingProgress;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\PublicMediaUrl;
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

    /**
     * Payload for the full-screen Welcome onboarding screen (separate concept
     * from the step-by-step TOURS above — a single one-time greeting, not a
     * spotlight tour). Merged into the shared `onboarding` Inertia prop under
     * the `welcome` key.
     *
     * Early-returns (no department/coworker query) whenever the feature is
     * disabled or the account already saw it, since this payload is built on
     * every authenticated request — never let it become a hot-path N+1.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  bool  $force  When true, always build the full greeting payload
     *                       (used by /settings/onboarding preview) even if the
     *                       feature is off or the account already saw it.
     * @return array<string, mixed>
     */
    public function welcomePayload(SystemAccount $account, bool $force = false): array
    {
        $enabled = (bool) config('va.onboarding_welcome_enabled', true);
        $seen = $account->onboarding_seen_at !== null;

        if (! $force && (! $enabled || $seen)) {
            return ['enabled' => $enabled, 'seen' => $seen];
        }

        $account->loadMissing('employee.departments');
        $department = $account->employee?->departments
            ->sortByDesc(fn (Department $d) => (bool) $d->pivot?->getAttribute('is_active'))
            ->first();

        $coworkers = [];
        $coworkerTotal = 0;

        if ($department !== null) {
            $members = $department->members()
                ->where('employees.id', '!=', $account->employee_id)
                ->get();

            $coworkerTotal = $members->count();
            $coworkers = $members->take(9)->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'avatar' => PublicMediaUrl::fromPublicDisk($e->avatar_path),
            ])->all();
        }

        return [
            'enabled' => $enabled,
            'seen' => $seen,
            'employee_name' => $account->employee?->full_name ?? $account->display_name,
            'role' => $account->role->value,
            'role_label' => $account->role->label(),
            'department' => $department ? [
                'name' => $department->name,
                'color' => $department->color,
            ] : null,
            'coworkers' => $coworkers,
            'coworker_total' => $coworkerTotal,
        ];
    }

    /** Idempotent: only writes once, first time the welcome screen closes. */
    public function markWelcomeSeen(SystemAccount $account): void
    {
        if ($account->onboarding_seen_at !== null) {
            return;
        }

        $account->forceFill(['onboarding_seen_at' => now()])->save();
    }

    /** Super-admin action: let everyone see the welcome screen again. */
    public function resetWelcomeForAll(): int
    {
        return SystemAccount::query()
            ->whereNotNull('onboarding_seen_at')
            ->update(['onboarding_seen_at' => null]);
    }

    /** Clear the welcome "seen" flag for a single account. */
    public function resetWelcomeFor(SystemAccount $account): void
    {
        if ($account->onboarding_seen_at === null) {
            return;
        }

        $account->forceFill(['onboarding_seen_at' => null])->save();
    }
}
