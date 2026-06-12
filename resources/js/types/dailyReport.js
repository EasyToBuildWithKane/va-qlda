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
 *
 * @param {ReportProjectLink[]} localProjects
 * @param {ReportProjectLink[]|null|undefined} serverProjects
 */
export function mergeSpawnedTaskIds(localProjects, serverProjects) {
    if (!serverProjects?.length) {
        return;
    }

    serverProjects.forEach((serverProj) => {
        const localProj = localProjects.find((p) => p.id === serverProj.id);
        if (!localProj?.tasks?.length || !serverProj.tasks?.length) {
            return;
        }

        serverProj.tasks.forEach((serverTask) => {
            const key = serverTask._localKey;
            if (!key || serverTask.id <= 0) {
                return;
            }

            const localTask = localProj.tasks.find((t) => t._localKey === key);
            if (localTask && localTask.id === 0) {
                localTask.id = serverTask.id;
            }
        });
    });
}
