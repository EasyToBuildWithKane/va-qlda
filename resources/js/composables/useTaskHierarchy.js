/**
 * Phân cấp công việc cha — con (chỉ 1 cấp, parent_id / subtasks).
 */

export const MAX_SUBTASK_DEPTH = 1;

/**
 * @param {object} task
 */
export function isSubtask(task) {
    return task != null && task.parent_id != null && task.parent_id !== '';
}

/**
 * @param {object} task
 */
export function isRootTask(task) {
    return !isSubtask(task);
}

/**
 * @param {object[]} tasks
 */
export function filterRootTasks(tasks) {
    return (tasks || []).filter(isRootTask);
}

/**
 * @param {object} child
 * @param {object[]} allTasks
 */
export function findParentTask(child, allTasks = []) {
    if (!child?.parent_id) return null;
    return (allTasks || []).find((t) => t.id === child.parent_id) ?? child.parent ?? null;
}

/**
 * Chỉ con trực tiếp (cấp 1).
 * @param {object} parent
 * @param {object[]} allTasks
 */
export function getDirectChildren(parent, allTasks = []) {
    const parentId = parent?.id;
    if (parentId == null) return [];

    const map = new Map();
    (parent.subtasks || []).forEach((t) => {
        if (t?.id != null) map.set(t.id, t);
    });
    (allTasks || []).forEach((t) => {
        if (t?.parent_id === parentId) map.set(t.id, { ...t, ...map.get(t.id) });
    });

    return [...map.values()].sort(
        (a, b) => (a.order_column ?? 0) - (b.order_column ?? 0) || a.id - b.id,
    );
}

/** @deprecated alias */
export const getTaskChildren = getDirectChildren;

/**
 * Lịch (ngày) — con luôn theo cha.
 * @param {object} task
 * @param {object[]} [allTasks]
 * @param {object} [parent]
 */
export function getTaskSchedule(task, allTasks = [], parent = null) {
    if (!isSubtask(task)) {
        return {
            start_date: task?.start_date ?? null,
            due_date: task?.due_date ?? null,
        };
    }
    const p = parent ?? findParentTask(task, allTasks);
    return {
        start_date: p?.start_date ?? null,
        due_date: p?.due_date ?? null,
        inherited: true,
    };
}

/**
 * @param {object} parent
 * @param {object[]} allTasks
 */
export function getSubtaskStats(parent, allTasks = []) {
    const children = getDirectChildren(parent, allTasks);
    if (!children.length) return null;
    const done = children.filter((c) => c.status?.value === 'done').length;
    const hours = children.reduce((s, c) => s + (Number(c.estimate_hours) || 0), 0);
    return { total: children.length, done, hours };
}

/**
 * Khối cha + con để render (Sprint collapse).
 * @param {object[]} roots
 * @param {object[]} allTasks
 */
export function buildParentBlocks(roots, allTasks = roots) {
    return filterRootTasks(roots).map((parent) => ({
        parent,
        children: getDirectChildren(parent, allTasks),
    }));
}

/**
 * Dòng phẳng tối đa 1 cấp con (timeline / bảng khi không collapse).
 * @param {object[]} roots
 * @param {object[]} [allTasks]
 * @param {{ includeSubtasks?: boolean }} [opts]
 */
export function buildTaskDisplayRows(roots, allTasks = roots, opts = {}) {
    const includeSubtasks = opts.includeSubtasks !== false;
    const rows = [];

    filterRootTasks(roots).forEach((parent) => {
        rows.push({ task: parent, depth: 0, isSubtask: false, parent: null });
        if (!includeSubtasks) return;
        getDirectChildren(parent, allTasks).forEach((child) => {
            rows.push({ task: child, depth: 1, isSubtask: true, parent });
        });
    });

    return rows;
}

/**
 * @param {object[]} roots
 * @param {object[]} allTasks
 */
export function countTaskTree(roots, allTasks) {
    return buildTaskDisplayRows(roots, allTasks, { includeSubtasks: true }).length;
}

/**
 * Chỉ đếm task gốc (Kanban cột).
 * @param {object[]} roots
 */
export function countRootTasks(roots) {
    return filterRootTasks(roots).length;
}
