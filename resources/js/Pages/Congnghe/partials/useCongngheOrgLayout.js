/**
 * Phân nhánh sơ đồ landing Công nghệ — hàng lãnh đạo ngang vs nhóm nghiệp vụ.
 */

/** @param {string|null|undefined} title */
export function isCongngheLeadershipSection(title) {
    const t = (title ?? '').trim().toLowerCase();
    if (!t) {
        return false;
    }

    return (
        t.includes('trưởng')
        || t.includes('phó')
        || t.includes('giám đốc')
        || t.includes('ban cntt')
        || t.includes('bộ phận')
        || t.includes('quản lý')
    );
}

/**
 * @param {{ leader: object|null, sectionGroups: Array<{ key: string, title: string|null, people: object[] }> }} roster
 * @returns {{ key: string, branchTitle: string, person: object }[]}
 */
export function buildLeadershipCards(roster) {
    /** @type {{ key: string, branchTitle: string, person: object }[]} */
    const cards = [];

    for (const group of roster.sectionGroups) {
        if (!isCongngheLeadershipSection(group.title)) {
            continue;
        }
        const branchTitle = (group.title ?? '').trim() || 'Lãnh đạo';
        for (const person of group.people) {
            cards.push({
                key: `${group.key}-${person.key}`,
                branchTitle,
                person,
            });
        }
    }

    const leader = roster.leader;
    if (leader) {
        const already = cards.some((c) => c.person.employeeId === leader.employeeId);
        if (!already) {
            cards.unshift({
                key: `leader-${leader.key}`,
                branchTitle: leader.roleTitle || 'Quản lý đơn vị',
                person: leader,
            });
        }
    }

    return cards;
}

/**
 * @param {{ sectionGroups: Array<{ key: string, title: string|null, people: object[] }> }} roster
 */
export function staffSectionGroups(roster) {
    return roster.sectionGroups.filter(
        (g) => g.people.length > 0 && !isCongngheLeadershipSection(g.title),
    );
}
