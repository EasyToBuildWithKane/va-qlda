<?php

namespace App\Support;

use App\Http\Resources\OrgTeamResource;
use App\Models\OrgTeam;
use Illuminate\Support\Collection;

class OrgTeamTreeBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forest(): array
    {
        $teams = OrgTeam::query()
            ->with(['leader', 'sections', 'members.employee', 'members.section'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $teams->groupBy(fn (OrgTeam $t) => $t->parent_id ?? 0);

        return self::buildChildren($byParent, 0)
            ->map(fn (OrgTeam $node) => self::toNode($node, $byParent))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<int, OrgTeam>>  $byParent
     * @return list<array<string, mixed>>
     */
    private static function toNode(OrgTeam $team, Collection $byParent): array
    {
        $data = (new OrgTeamResource($team))->resolve();
        $children = self::buildChildren($byParent, $team->id)
            ->map(fn (OrgTeam $child) => self::toNode($child, $byParent))
            ->values()
            ->all();

        $data['children'] = $children;

        return $data;
    }

    /**
     * @param  Collection<int, Collection<int, OrgTeam>>  $byParent
     * @return Collection<int, OrgTeam>
     */
    private static function buildChildren(Collection $byParent, int $parentKey): Collection
    {
        return $byParent->get($parentKey, collect());
    }
}
