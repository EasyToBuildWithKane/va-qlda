import { computed } from 'vue';

/**
 * Inertia / JSON đôi khi trả collection dạng object — chuẩn hoá thành mảng để v-for / for...of.
 * @param {unknown} value
 * @returns {object[]}
 */
export function toIterableList(value) {
    if (Array.isArray(value)) {
        return value;
    }
    if (value && typeof value === 'object') {
        const record = /** @type {Record<string, unknown>} */ (value);
        if (Array.isArray(record.data)) {
            return record.data;
        }

        return Object.values(record).filter(
            (item) => item !== null && typeof item === 'object',
        );
    }

    return [];
}

/**
 * @typedef {{ key: string, name: string, avatar: string|null, role: string|null, isLeader: boolean }} OrgTeamPerson
 * @typedef {{ label: string, people: OrgTeamPerson[] }} OrgTeamMemberBranch
 */

/**
 * @param {import('vue').MaybeRefOrGetter<{ leader?: object, members?: unknown }>} nodeOrProps
 */
export function useOrgTeamRoster(nodeOrProps) {
    return computed(() => {
        const source = typeof nodeOrProps === 'function'
            ? nodeOrProps()
            : nodeOrProps;
        const leaderRaw = source?.leader ?? null;
        const members = toIterableList(source?.members);
        const leaderId = leaderRaw?.id;

        /** @type {OrgTeamPerson|null} */
        let leader = null;
        if (leaderRaw) {
            leader = {
                key: `leader-${leaderRaw.id}`,
                name: leaderRaw.name,
                avatar: leaderRaw.avatar_path,
                role: 'Trưởng nhóm',
                isLeader: true,
            };
        }

        /** @type {Map<string, OrgTeamPerson[]>} */
        const branchMap = new Map();

        for (const m of members) {
            const emp = m.employee;
            if (!emp?.id) {
                continue;
            }
            if (leaderId && emp.id === leaderId) {
                continue;
            }

            const branchLabel = m.branch?.label || 'Thành viên';
            const person = {
                key: `member-${m.id}`,
                name: emp.name,
                avatar: emp.avatar_path,
                role: branchLabel,
                isLeader: false,
            };

            if (!branchMap.has(branchLabel)) {
                branchMap.set(branchLabel, []);
            }
            branchMap.get(branchLabel).push(person);
        }

        /** @type {OrgTeamMemberBranch[]} */
        const branches = Array.from(branchMap.entries()).map(([label, people]) => ({
            label,
            people,
        }));

        const memberCount = branches.reduce((sum, b) => sum + b.people.length, 0);
        const totalCount = (leader ? 1 : 0) + memberCount;

        return {
            leader,
            branches,
            memberCount,
            totalCount,
        };
    });
}

/** @deprecated Dùng useOrgTeamRoster — giữ để tương thích nếu cần danh sách phẳng */
export function useOrgTeamPeople(nodeOrProps) {
    const roster = useOrgTeamRoster(nodeOrProps);

    return computed(() => {
        const list = [];
        if (roster.value.leader) {
            list.push(roster.value.leader);
        }
        for (const branch of roster.value.branches) {
            list.push(...branch.people);
        }

        return list;
    });
}
