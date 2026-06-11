import { computed } from 'vue';
import { getAssignees } from '@/composables/useSprintFilters';
import { isSprintOverdue } from '@/composables/useSprintWorkspace';
import {
    getTaskEstimateOverrunHours,
    isTaskDateOverdue,
    isTaskEstimateOverrun,
    isTaskOverdue,
} from '@/composables/useTaskTimeliness';

export function useSprintReconcile(sprintsSource, tasksSource) {
    const issues = computed(() => {
        const sprints = sprintsSource.value ?? [];
        const tasks = tasksSource.value ?? [];
        const result = [];

        sprints.forEach((s) => {
            const st = tasks.filter((t) => t.sprint_id === s.id);
            if (!st.length) {
                result.push({
                    level: 'warning',
                    code: 'sprint_empty',
                    message: `Sprint "${s.name}" chưa có công việc`,
                    sprintId: s.id,
                });
            }
            if (isSprintOverdue(s)) {
                result.push({
                    level: 'error',
                    code: 'sprint_overdue',
                    message: `Sprint "${s.name}" đã quá hạn`,
                    sprintId: s.id,
                });
            }
        });

        const sprintRanges = sprints
            .filter((s) => s.start_date && s.end_date)
            .map((s) => ({ ...s, start: new Date(`${s.start_date}T00:00:00`), end: new Date(`${s.end_date}T00:00:00`) }));
        for (let i = 0; i < sprintRanges.length; i++) {
            for (let j = i + 1; j < sprintRanges.length; j++) {
                const a = sprintRanges[i];
                const b = sprintRanges[j];
                if (a.start <= b.end && b.start <= a.end) {
                    result.push({
                        level: 'warning',
                        code: 'sprint_overlap',
                        message: `Sprint "${a.name}" và "${b.name}" chồng thời gian`,
                    });
                }
            }
        }

        const titles = new Map();
        tasks.forEach((t) => {
            const key = (t.title || '').trim().toLowerCase();
            if (!key) return;
            if (!titles.has(key)) titles.set(key, []);
            titles.get(key).push(t);

            if (!getAssignees(t).length && !t.assignee) {
                result.push({
                    level: 'error',
                    code: 'no_assignee',
                    message: `Task #${t.id} "${t.title}" chưa có người phụ trách`,
                    taskId: t.id,
                });
            }

            if (isTaskDateOverdue(t)) {
                result.push({
                    level: 'error',
                    code: 'task_due_overdue',
                    message: `Task #${t.id} "${t.title}" quá hạn xử lý (ngày hạn đã qua)`,
                    taskId: t.id,
                });
            }

            if (isTaskEstimateOverrun(t)) {
                const overH = getTaskEstimateOverrunHours(t);
                result.push({
                    level: 'error',
                    code: 'task_estimate_overrun',
                    message: overH
                        ? `Task #${t.id} "${t.title}" vượt giờ ước tính (~${overH}h) — cần cập nhật trạng thái sớm hơn`
                        : `Task #${t.id} "${t.title}" vượt giờ ước tính so với thời gian bắt đầu làm`,
                    taskId: t.id,
                });
            }

            if (
                !isTaskOverdue(t)
                && Number(t.estimate_hours) > 0
                && ['in_progress', 'in_review', 'blocked'].includes(t.status?.value)
                && !t.work_started_at
                && !t.start_date
            ) {
                result.push({
                    level: 'info',
                    code: 'estimate_sla_unknown',
                    message: `Task #${t.id} có giờ ước tính nhưng chưa có ngày bắt đầu — SLA giờ có thể không chính xác`,
                    taskId: t.id,
                });
            }

            const est = Number(t.estimate_hours);
            if (est > 80) {
                result.push({
                    level: 'warning',
                    code: 'estimate_high',
                    message: `Task #${t.id} có giờ ước tính bất thường (${est}h)`,
                    taskId: t.id,
                });
            }

            if (!t.due_date && !t.start_date) {
                result.push({
                    level: 'info',
                    code: 'missing_dates',
                    message: `Task #${t.id} thiếu ngày bắt đầu/hạn`,
                    taskId: t.id,
                });
            }
        });

        titles.forEach((list, _title) => {
            if (list.length > 1) {
                result.push({
                    level: 'warning',
                    code: 'duplicate_title',
                    message: `Trùng tiêu đề "${list[0].title}" (${list.length} task)`,
                    taskIds: list.map((t) => t.id),
                });
            }
        });

        sprints.forEach((s) => {
            const st = tasks.filter((t) => t.sprint_id === s.id);
            const cap = st.reduce((a, t) => a + (Number(t.estimate_hours) || 0), 0);
            if (cap > 200) {
                result.push({
                    level: 'warning',
                    code: 'capacity_high',
                    message: `Sprint "${s.name}" vượt ngưỡng capacity (${cap}h)`,
                    sprintId: s.id,
                });
            }
        });

        return result;
    });

    const summary = computed(() => ({
        errors: issues.value.filter((i) => i.level === 'error').length,
        warnings: issues.value.filter((i) => i.level === 'warning').length,
        info: issues.value.filter((i) => i.level === 'info').length,
        total: issues.value.length,
    }));

    return { issues, summary };
}
