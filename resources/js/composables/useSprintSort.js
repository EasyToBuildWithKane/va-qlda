/** Thứ tự sprint: chỉ theo sort_order (kéo thả), tie-break id — khớp Project::sprints(). */
/** @param {Array<{ id: number, sort_order?: number }>} list */
export function sortSprints(list) {
    return [...list].sort(compareSprints);
}

/** @param {{ id: number, sort_order?: number }} a */
/** @param {{ id: number, sort_order?: number }} b */
export function compareSprints(a, b) {
    const oa = a.sort_order ?? 0;
    const ob = b.sort_order ?? 0;
    if (oa !== ob) return oa - ob;
    return a.id - b.id;
}
