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

/** @param {string|null|undefined} title */
function leadershipSortRank(title) {
    const t = (title ?? '').trim().toLowerCase();
    if (t.includes('trưởng ban')) {
        return 10;
    }
    if (t.includes('trưởng bộ phận') || t.includes('bộ phận')) {
        return 20;
    }
    if (t.includes('phó')) {
        return 30;
    }
    if (t.includes('trưởng')) {
        return 40;
    }
    return 50;
}

/** @param {string|null|undefined} value */
function normalizeTitle(value) {
    return (value ?? '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

/**
 * @param {string|null|undefined} branchTitle
 * @param {string|null|undefined} roleTitle
 */
export function shouldShowRoleSubtitle(branchTitle, roleTitle) {
    const role = (roleTitle ?? '').trim();
    if (!role) {
        return false;
    }
    const branch = (branchTitle ?? '').trim();
    if (!branch) {
        return true;
    }
    const b = normalizeTitle(branch);
    const r = normalizeTitle(role);
    if (b === r) {
        return false;
    }
    if (b.length >= 4 && r.length >= 4 && (b.includes(r) || r.includes(b))) {
        return false;
    }
    return true;
}

/** @param {number|string|null|undefined} id */
function employeeIdKey(id) {
    const n = Number(id);
    return Number.isFinite(n) ? n : null;
}

/** @param {{ key: string, branchTitle: string, person: object }[]} cards */
function dedupeLeadershipCards(cards) {
    const seen = new Set();
    return cards.filter((card) => {
        const key = employeeIdKey(card.person?.employeeId);
        if (key == null) {
            return true;
        }
        if (seen.has(key)) {
            return false;
        }
        seen.add(key);
        return true;
    });
}

/**
 * @param {{ leader: object|null, sectionGroups: Array<{ key: string, title: string|null, people: object[] }> }} roster
 * @param {{ managerCard: object|null, tierCards: object[] }|null} leadershipLayout
 */
function leadershipEmployeeIds(roster, leadershipLayout = null) {
    const ids = new Set();
    const add = (person) => {
        const key = employeeIdKey(person?.employeeId);
        if (key != null) {
            ids.add(key);
        }
    };

    add(roster.leader);
    if (leadershipLayout) {
        if (leadershipLayout.managerCard) {
            add(leadershipLayout.managerCard.person);
        }
        for (const card of leadershipLayout.tierCards ?? []) {
            add(card.person);
        }
    } else {
        for (const group of roster.sectionGroups) {
            if (!isCongngheLeadershipSection(group.title)) {
                continue;
            }
            for (const person of group.people) {
                add(person);
            }
        }
    }

    return ids;
}

/**
 * @param {{ leader: object|null, sectionGroups: Array<{ key: string, title: string|null, people: object[] }> }} roster
 * @param {{ nestedBranch?: boolean }} options
 */
export function buildCongngheLeadershipLayout(roster, options = {}) {
    const nestedBranch = Boolean(options.nestedBranch);
    const leader = roster.leader;
    const leaderId = employeeIdKey(leader?.employeeId);

    /** @type {{ key: string, branchTitle: string, person: object, sort: number }[]} */
    const fromSections = [];

    for (const group of roster.sectionGroups) {
        if (!isCongngheLeadershipSection(group.title)) {
            continue;
        }
        const branchTitle = (group.title ?? '').trim() || 'Lãnh đạo';
        const sort = leadershipSortRank(group.title);
        for (const person of group.people) {
            if (leaderId != null && employeeIdKey(person.employeeId) === leaderId) {
                continue;
            }
            fromSections.push({
                key: `${group.key}-${person.key}`,
                branchTitle,
                person,
                sort,
            });
        }
    }

    fromSections.sort((a, b) => a.sort - b.sort || a.branchTitle.localeCompare(b.branchTitle, 'vi'));

    if (nestedBranch) {
        const tierCards = fromSections.map(({ key, branchTitle, person }) => ({
            key, branchTitle, person,
        }));

        if (leader) {
            const leaderKey = employeeIdKey(leader.employeeId);
            const alreadyListed = leaderKey != null
                && tierCards.some((c) => employeeIdKey(c.person.employeeId) === leaderKey);
            if (!alreadyListed) {
                tierCards.unshift({
                    key: `leader-${leader.key}`,
                    branchTitle: 'Trưởng nhóm',
                    person: leader,
                });
            }
        }

        return {
            managerCard: null,
            tierCards: dedupeLeadershipCards(tierCards),
            leadershipEyebrow: 'Trưởng nhóm',
        };
    }

    const managerCard = leader
        ? {
            key: `leader-${leader.key}`,
            branchTitle: 'Quản lý',
            person: leader,
        }
        : null;

    const tierCards = dedupeLeadershipCards(fromSections.map(({ key, branchTitle, person }) => ({
        key, branchTitle, person,
    })));

    return {
        managerCard,
        tierCards,
        leadershipEyebrow: 'Ban lãnh đạo',
    };
}

/** @deprecated */
export function buildLeadershipCards(roster) {
    const { managerCard, tierCards } = buildCongngheLeadershipLayout(roster, { nestedBranch: false });
    return managerCard ? [managerCard, ...tierCards] : tierCards;
}

/**
 * @param {{ leader: object|null, sectionGroups: Array<{ key: string, title: string|null, people: object[] }> }} roster
 * @param {{ managerCard: object|null, tierCards: object[] }|null} [leadershipLayout]
 */
export function staffSectionGroups(roster, leadershipLayout = null) {
    const excludeIds = leadershipEmployeeIds(roster, leadershipLayout);

    return roster.sectionGroups
        .filter((g) => g.people.length > 0 && !isCongngheLeadershipSection(g.title))
        .map((g) => ({
            ...g,
            people: g.people.filter((p) => {
                const key = employeeIdKey(p.employeeId);
                return key == null || !excludeIds.has(key);
            }),
        }))
        .filter((g) => g.people.length > 0);
}
