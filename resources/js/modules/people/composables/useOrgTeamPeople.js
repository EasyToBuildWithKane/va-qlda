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
 * @param {import('vue').MaybeRefOrGetter<{ leader?: object, members?: unknown }>} nodeOrProps
 */
export function useOrgTeamPeople(nodeOrProps) {
    return computed(() => {
        const source = typeof nodeOrProps === 'function'
            ? nodeOrProps()
            : nodeOrProps;
        const leader = source?.leader ?? null;
        const members = toIterableList(source?.members);
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
