import { computed, unref } from 'vue';

/**
 * @param {import('vue').MaybeRefOrGetter<{ leader?: object, members?: object[] }>} nodeOrProps
 */
export function useOrgTeamPeople(nodeOrProps) {
    return computed(() => {
        const source = typeof nodeOrProps === 'function'
            ? nodeOrProps()
            : unref(nodeOrProps);
        const leader = source?.leader ?? null;
        const members = source?.members ?? [];
        const leaderId = leader?.id;
        const list = [];

        if (leader) {
            list.push({
                key: `leader-${leader.id}`,
                name: leader.name,
                avatar: leader.avatar_path,
                role: 'Trưởng nhóm',
                isLeader: true,
            });
        }

        for (const m of members) {
            const emp = m.employee;
            if (!emp?.id) {
                continue;
            }
            if (leaderId && emp.id === leaderId) {
                continue;
            }
            list.push({
                key: `member-${m.id}`,
                name: emp.name,
                avatar: emp.avatar_path,
                role: m.branch?.label || null,
                isLeader: false,
            });
        }

        return list;
    });
}
