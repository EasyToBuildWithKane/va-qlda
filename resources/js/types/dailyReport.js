/**
 * @typedef {Object} ReportTaskLink
 * @property {number} id
 * @property {string} title
 * @property {string} [status]
 * @property {string} [_localKey]
 * @property {string} [note]
 * @property {string|number} [estimated_hours]
 */

/**
 * @typedef {Object} ReportProjectLink
 * @property {number} id
 * @property {string} name
 * @property {string} [code]
 * @property {ReportTaskLink[]} [tasks]
 */

/**
 * After save/autosave, copy server-assigned ids onto local spawned rows matched by `_localKey`.
 * Mutates `localProjects` in place; does not replace project/task objects.
 * Supports numeric task ids (project spawn) and UUID strings (routine_tasks).
 *
 * @param {ReportProjectLink[]} localProjects
 * @param {ReportProjectLink[]|null|undefined} serverProjects
 */
export function mergeSpawnedTaskIds(localProjects, serverProjects) {
    if (!serverProjects?.length) {
        return;
    }

    const hasPersistedId = (id) => {
        if (typeof id === 'string') {
            const trimmed = id.trim();
            return trimmed !== '' && trimmed !== '0';
        }
        return Number(id) > 0;
    };

    const isPlaceholderId = (id) => !hasPersistedId(id);

    serverProjects.forEach((serverProj) => {
        const localProj = localProjects.find((p) => p.id === serverProj.id);
        if (!localProj?.tasks?.length || !serverProj.tasks?.length) {
            return;
        }

        serverProj.tasks.forEach((serverTask) => {
            const key = serverTask._localKey;
            if (!key || !hasPersistedId(serverTask.id)) {
                return;
            }

            const localTask = localProj.tasks.find((t) => t._localKey === key);
            if (localTask && isPlaceholderId(localTask.id)) {
                localTask.id = serverTask.id;
                if (serverTask.status) {
                    localTask.status = serverTask.status;
                }
            }
        });
    });
}
