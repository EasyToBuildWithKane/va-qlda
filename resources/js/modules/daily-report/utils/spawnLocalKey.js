/** @returns {string} Stable client id for matching spawned tasks across autosave round-trips. */
export function createSpawnLocalKey() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `spawn-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
}
