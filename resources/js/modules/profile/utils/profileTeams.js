/**
 * @param {Array<{ name?: string, is_leader?: boolean }>|null|undefined} teams
 * @returns {string|null}
 */
export function formatProfileProjectTeams(teams) {
    const list = teams ?? [];
    if (list.length === 0) {
        return null;
    }

    return list
        .map((t) => {
            const name = (t.name ?? '').trim();
            if (!name) {
                return null;
            }
            return t.is_leader ? `${name} · Quản lý nhóm` : name;
        })
        .filter(Boolean)
        .join(', ');
}
