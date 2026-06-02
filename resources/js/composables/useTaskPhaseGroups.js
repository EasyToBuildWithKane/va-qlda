/** Giai đoạn SDLC — dùng chung tab Sprint & Tiến độ/Gantt. */

export const TASK_PHASE_DEFS = [
    { key: 'discovery', label: 'Discovery', match: /discovery|khảo sát|kickoff/i },
    { key: 'analysis', label: 'Analysis', match: /analysis|phân tích|brd|requirement/i },
    { key: 'design', label: 'Design', match: /design|ui|ux|mockup|figma/i },
    { key: 'development', label: 'Development', match: /dev|develop|code|implement|api|backend|frontend/i },
    { key: 'testing', label: 'Testing', match: /test|qa|qc|bug fix/i },
    { key: 'uat', label: 'UAT', match: /uat|acceptance|nghiệm thu/i },
    { key: 'deployment', label: 'Deployment', match: /deploy|release|go.?live|production/i },
    { key: 'maintenance', label: 'Maintenance', match: /maintain|support|hotfix/i },
];

/**
 * @param {object} task
 * @returns {string}
 */
export function detectTaskPhase(task) {
    if (task?.phase?.value) return task.phase.value;
    if (typeof task?.phase === 'string') return task.phase;
    const hay = `${task?.title || ''} ${task?.sprint?.name || ''} ${task?.description || ''}`;
    for (const p of TASK_PHASE_DEFS) {
        if (p.match.test(hay)) return p.key;
    }
    return 'development';
}

/**
 * @param {string} phaseKey
 * @param {object} [sampleTask]
 */
export function phaseLabelFor(phaseKey, sampleTask) {
    if (sampleTask?.phase?.label) return sampleTask.phase.label;
    const def = TASK_PHASE_DEFS.find((p) => p.key === phaseKey);
    if (def) return def.label;
    return phaseKey === 'general' ? 'Khác' : phaseKey;
}

/**
 * @param {object[]} taskList
 * @returns {{ key: string, label: string, tasks: object[] }[]}
 */
export function groupTasksByPhase(taskList) {
    const map = new Map(TASK_PHASE_DEFS.map((p) => [p.key, { key: p.key, label: p.label, tasks: [] }]));
    map.set('general', { key: 'general', label: 'Khác', tasks: [] });

    (taskList || []).forEach((t) => {
        const key = detectTaskPhase(t);
        const bucket = map.get(key) || map.get('general');
        bucket.tasks.push(t);
    });

    const order = [...TASK_PHASE_DEFS.map((p) => p.key), 'general'];
    return order
        .map((key) => map.get(key))
        .filter((g) => g && g.tasks.length > 0)
        .map((g) => ({
            ...g,
            label: phaseLabelFor(g.key, g.tasks[0]),
        }));
}
