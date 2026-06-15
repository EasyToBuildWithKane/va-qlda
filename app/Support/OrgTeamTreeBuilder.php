<?php

namespace App\Support;

use App\Http\Resources\OrgTeamMemberResource;
use App\Http\Resources\OrgTeamResource;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrgTeamTreeBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forest(): array
    {
        $teams = OrgTeam::query()
            ->with(['leader', 'sections', 'members.employee', 'members.section'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $teams->groupBy(fn (OrgTeam $t) => $t->parent_id ?? 0);
        /** @var Collection<int, Collection<int, OrgTeam>> $byParent */
        $byParent = Collection::make($byParent->all());

        $result = self::buildChildren($byParent, 0)
            ->map(fn (OrgTeam $node) => self::toNode($node, $byParent))
            ->values()
            ->all();

        /** @var array<int, array<string, mixed>> $result */
        return $result;
    }

    /**
     * Cây sơ đồ cho landing Phòng Công nghệ — chỉ nhóm gốc khớp {@see config va.congnghe_org_root_patterns}.
     * Nếu không khớp và chỉ có một nhóm gốc trong hệ thống, trả về nhóm đó.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function congngheForest(): array
    {
        $forest = self::forest();

        $rootTeamId = config('va.congnghe_org_root_team_id');
        if (is_numeric($rootTeamId) && (int) $rootTeamId > 0) {
            $byId = self::findNodeById($forest, (int) $rootTeamId);
            if ($byId !== null) {
                return [$byId];
            }
        }

        $patterns = config('va.congnghe_org_root_patterns', []);
        if (! is_array($patterns) || $patterns === []) {
            return $forest;
        }

        $matched = array_values(array_filter(
            $forest,
            fn (array $node): bool => self::nameMatchesPatterns((string) ($node['name'] ?? ''), $patterns),
        ));

        if ($matched !== []) {
            return $matched;
        }

        $deep = self::findFirstMatchingNode($forest, $patterns);
        if ($deep !== null) {
            return [$deep];
        }

        $broadPatterns = [
            'cong nghe',
            'cntt',
            'cng',
            'information',
            'technology',
            'it',
        ];

        $broad = array_values(array_filter(
            $forest,
            fn (array $node): bool => self::nameMatchesPatterns((string) ($node['name'] ?? ''), $broadPatterns),
        ));
        if ($broad !== []) {
            return $broad;
        }

        $deepBroad = self::findFirstMatchingNode($forest, $broadPatterns);
        if ($deepBroad !== null) {
            return [$deepBroad];
        }

        return count($forest) === 1 ? $forest : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    public static function findNodeById(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if ((int) ($node['id'] ?? 0) === $id) {
                return $node;
            }
            $children = $node['children'] ?? [];
            if (is_array($children)) {
                $found = self::findNodeById($children, $id);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  list<string>  $patterns
     */
    private static function findFirstMatchingNode(array $nodes, array $patterns): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (self::nameMatchesPatterns((string) ($node['name'] ?? ''), $patterns)) {
                return $node;
            }
            $children = $node['children'] ?? [];
            if (is_array($children)) {
                $found = self::findFirstMatchingNode($children, $patterns);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    public static function nameMatchesPatterns(string $name, array $patterns): bool
    {
        $norm = Str::lower(Str::ascii($name));
        foreach ($patterns as $pattern) {
            $needle = Str::lower(Str::ascii((string) $pattern));
            if ($needle !== '' && str_contains($norm, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Collection<int, OrgTeam>>  $byParent
     * @return list<array<string, mixed>>
     */
    private static function toNode(OrgTeam $team, Collection $byParent): array
    {
        $request = request();
        $data = (new OrgTeamResource($team))->toArray($request);

        if ($team->relationLoaded('members')) {
            $data['members'] = $team->members
                ->map(fn (OrgTeamMember $member) => (new OrgTeamMemberResource($member))->toArray($request))
                ->values()
                ->all();
        }

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
