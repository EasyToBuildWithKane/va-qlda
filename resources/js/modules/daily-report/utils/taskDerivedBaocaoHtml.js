import { ROUTINE_PROJECT_NAME, isRoutineProjectEntry } from '@/modules/daily-report/constants/routineWork';

const TASK_STATUS_LABELS = {
    todo: 'Cần làm',
    in_progress: 'Đang làm',
    in_review: 'Đang review',
    done: 'Hoàn thành',
    blocked: 'Bị chặn',
};

const escapeHtml = (s) =>
    String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** Collapse whitespace for comparing auto-filled vs stored rich text. */
export function normalizeRichTextHtml(html) {
    return String(html ?? '')
        .replace(/>\s+</g, '><')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Resolve task status from report `projects` JSON (frozen at submit) or inline on the task row.
 *
 * @param {import('@/types/dailyReport').ReportProjectLink[]} projects
 * @param {number} projectId
 * @param {number} taskId
 * @param {string|undefined} inlineStatus
 */
export function taskStatusFromReportProjects(projects, projectId, taskId, inlineStatus) {
    if (isRoutineProjectEntry({ id: projectId })) {
        return inlineStatus ?? 'todo';
    }
    const project = (projects ?? []).find((p) => p.id === projectId);
    const task = project?.tasks?.find((t) => t.id === taskId);
    return task?.status ?? inlineStatus ?? 'todo';
}

/**
 * @param {import('@/types/dailyReport').ReportProjectLink[]} projects
 * @param {(status: string) => boolean} predicate
 * @param {boolean} showStatus
 * @param {(projectId: number, taskId: number, inlineStatus?: string) => string|null|undefined} [resolveStatus]
 */
export function buildTaskListBaocaoHtml(projects, predicate, showStatus = true, resolveStatus) {
    const statusOf = resolveStatus
        ?? ((projectId, taskId, inline) => taskStatusFromReportProjects(projects, projectId, taskId, inline));
    const blocks = [];
    for (const p of projects ?? []) {
        const tasks = (p.tasks || []).filter((t) => {
            const st = statusOf(p.id, t.id, t.status) ?? 'todo';
            return predicate(st);
        });
        if (!tasks.length) continue;
        const items = tasks.map((t) => {
            const st = statusOf(p.id, t.id, t.status) ?? 'todo';
            const tag = showStatus && st ? ` — ${TASK_STATUS_LABELS[st] || st}` : '';
            return `<li>${escapeHtml(t.title)}${tag}</li>`;
        }).join('');
        const heading = isRoutineProjectEntry(p) ? ROUTINE_PROJECT_NAME : p.name;
        blocks.push(`<p><strong>${escapeHtml(heading)}</strong></p><ul>${items}</ul>`);
    }
    return blocks.join('');
}

export function buildAutoGoalsHtml(projects, resolveStatus) {
    return buildTaskListBaocaoHtml(projects, (st) => st === 'todo', false, resolveStatus);
}

export function buildAutoProgressHtml(projects, resolveStatus) {
    return buildTaskListBaocaoHtml(projects, (st) => st !== 'todo', true, resolveStatus);
}

/**
 * True when goals/progress fields still match the auto-generated lists from selected tasks
 * (Today.vue sync) — showing both structured scope and báo cáo fields would duplicate content.
 */
export function baocaoFieldsMirrorSelectedTasks(projects, goalsToday, progressUpdate) {
    const goalsBuilt = buildAutoGoalsHtml(projects);
    const progressBuilt = buildAutoProgressHtml(projects);
    const hasTodoTasks = goalsBuilt !== '';
    const hasProgressTasks = progressBuilt !== '';

    if (!hasTodoTasks && !hasProgressTasks) {
        return false;
    }

    const goalsNorm = normalizeRichTextHtml(goalsToday);
    const progressNorm = normalizeRichTextHtml(progressUpdate);

    const goalsMatch = !hasTodoTasks || goalsNorm === normalizeRichTextHtml(goalsBuilt);
    const progressMatch = !hasProgressTasks || progressNorm === normalizeRichTextHtml(progressBuilt);

    return goalsMatch && progressMatch;
}
