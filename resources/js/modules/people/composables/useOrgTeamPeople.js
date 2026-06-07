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
 * @typedef {{ key: string, title: string|null, people: OrgTeamPerson[] }} OrgTeamSectionGroup
 */

/**
 * @param {import('vue').MaybeRefOrGetter<{ leader?: object, members?: unknown, sections?: unknown }>} nodeOrProps
 */
export function useOrgTeamRoster(nodeOrProps) {
    return computed(() => {
        const source = typeof nodeOrProps === 'function'
            ? nodeOrProps()
            : nodeOrProps;
        const leaderRaw = source?.leader ?? null;
        const members = toIterableList(source?.members);
        const sections = toIterableList(source?.sections)
            .slice()
            .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
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

        /** @type {Map<number|null, OrgTeamPerson[]>} */
        const bySection = new Map();

        for (const m of members) {
            const emp = m.employee;
            if (!emp?.id) {
                continue;
            }
            if (leaderId && emp.id === leaderId) {
                continue;
            }

            const sectionId = m.section?.id ?? m.section_id ?? null;
            const person = {
                key: `member-${m.id}`,
                name: emp.name,
                avatar: emp.avatar_path,
                role: m.branch?.label || null,
                isLeader: false,
            };

            if (!bySection.has(sectionId)) {
                bySection.set(sectionId, []);
            }
            bySection.get(sectionId).push(person);
        }

        /** @type {OrgTeamSectionGroup[]} */
        const sectionGroups = [];

        for (const section of sections) {
            const people = bySection.get(section.id) ?? [];
            if (people.length === 0) {
                continue;
            }
            sectionGroups.push({
                key: `section-${section.id}`,
                title: section.title,
                people,
            });
            bySection.delete(section.id);
        }

        const unassigned = bySection.get(null) ?? [];
        if (unassigned.length > 0) {
            sectionGroups.push({
                key: 'section-unassigned',
                title: null,
                people: unassigned,
            });
        }

        for (const [sectionId, people] of bySection.entries()) {
            if (sectionId === null || !people.length) {
                continue;
            }
            sectionGroups.push({
                key: `section-orphan-${sectionId}`,
                title: people[0]?.role || 'Thành viên',
                people,
            });
        }

        const memberCount = sectionGroups.reduce((sum, g) => sum + g.people.length, 0);
        const totalCount = (leader ? 1 : 0) + memberCount;

        return {
            leader,
            sectionGroups,
            memberCount,
            totalCount,
        };
    });
}

/** @deprecated Dùng useOrgTeamRoster */
export function useOrgTeamPeople(nodeOrProps) {
    const roster = useOrgTeamRoster(nodeOrProps);

    return computed(() => {
        const list = [];
        if (roster.value.leader) {
            list.push(roster.value.leader);
        }
        for (const group of roster.value.sectionGroups) {
            list.push(...group.people);
        }

        return list;
    });
}
