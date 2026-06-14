import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

/**
 * Số người gắn trực tiếp với một nút team (trưởng + thành viên, không trùng leader).
 * @param {object} node
 */
export function directPeopleCount(node) {
    const leaderId = node?.leader?.id ?? null;
    let count = leaderId ? 1 : 0;
    for (const m of toIterableList(node?.members)) {
        const empId = m?.employee?.id;
        if (!empId || empId === leaderId) {
            continue;
        }
        count += 1;
    }

    return count;
}

/**
 * Thống kê toàn bộ cây con (đệ quy).
 * @param {object} node
 * @returns {{ teamCount: number, subGroupCount: number, peopleCount: number, maxDepth: number }}
 */
export function summarizeSubtree(node) {
    const children = toIterableList(node?.children);
    let teamCount = 1;
    let peopleCount = directPeopleCount(node);
    let maxDepth = node?.level ?? 1;

    for (const child of children) {
        const sub = summarizeSubtree(child);
        teamCount += sub.teamCount;
        peopleCount += sub.peopleCount;
        maxDepth = Math.max(maxDepth, sub.maxDepth);
    }

    return {
        teamCount,
        subGroupCount: children.length,
        peopleCount,
        maxDepth,
    };
}

/**
 * @param {object[]} trees
 */
export function summarizeForest(trees) {
    const roots = toIterableList(trees);
    let teamCount = 0;
    let peopleCount = 0;

    for (const root of roots) {
        const s = summarizeSubtree(root);
        teamCount += s.teamCount;
        peopleCount += s.peopleCount;
    }

    return {
        rootCount: roots.length,
        teamCount,
        peopleCount,
    };
}
