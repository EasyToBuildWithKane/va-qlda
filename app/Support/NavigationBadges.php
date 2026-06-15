<?php

namespace App\Support;

use App\Models\CongngheSoftwareProposal;
use App\Models\SystemAccount;
use App\Services\NotificationService;
use App\Support\Enums\CongngheSoftwareProposalStatus;

/**
 * Resolves live counters (unread notifications, proposals awaiting triage, …)
 * and attaches them to nav items that declare a `badgeKey`.
 *
 * Keeps {@see Navigation} a pure structural definition: only the badge keys that
 * survive role filtering are queried, so no role rules are duplicated here.
 */
class NavigationBadges
{
    /**
     * @param  array<int, array<string, mixed>>  $nav
     * @return array<int, array<string, mixed>>
     */
    public static function decorate(array $nav, SystemAccount $account): array
    {
        $keys = [];
        foreach ($nav as $group) {
            foreach ($group['items'] as $item) {
                if (isset($item['badgeKey'])) {
                    $keys[$item['badgeKey']] = true;
                }
            }
        }

        $counts = self::counts(array_keys($keys), $account);

        foreach ($nav as $gi => $group) {
            foreach ($group['items'] as $ii => $item) {
                $key = $item['badgeKey'] ?? null;
                unset($nav[$gi]['items'][$ii]['badgeKey']);

                if ($key !== null && ($counts[$key] ?? 0) > 0) {
                    $nav[$gi]['items'][$ii]['badge'] = $counts[$key];
                }
            }
        }

        return $nav;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private static function counts(array $keys, SystemAccount $account): array
    {
        $counts = [];

        foreach ($keys as $key) {
            $counts[$key] = match ($key) {
                'notifications_unread' => app(NotificationService::class)->unreadCount($account),
                'proposals_new' => CongngheSoftwareProposal::query()
                    ->where('status', CongngheSoftwareProposalStatus::New)
                    ->count(),
                default => 0,
            };
        }

        return $counts;
    }
}
