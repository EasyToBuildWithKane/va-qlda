import { computed } from 'vue';
import { getAssignees } from '@/composables/useSprintFilters';
import { isTaskDone, isTaskOverdue } from '@/composables/useTaskTimeliness';
import { filterRootTasks } from '@/composables/useTaskHierarchy';
import { completionPercentFromTasks } from '@/shared/utils/taskProgress';

export { isTaskDone, isTaskOverdue } from '@/composables/useTaskTimeliness';

const startOfToday = () => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
};

const parseDate = (x) => (x ? new Date(`${x}T00:00:00`) : null);

export function isTaskBlocked(t) {
    return t.status?.value === 'blocked';
}

export function isSprintOverdue(s) {
    const end = parseDate(s.end_date);
    return !!(end && end < startOfToday() && s.status?.value !== 'completed');
}

export function useSprintWorkspace(sprintsSource, tasksSource, projectSource) {
    const sprints = computed(() => sprintsSource.value ?? []);
    const tasks = computed(() => tasksSource.value ?? []);
    const rootTasks = computed(() => filterRootTasks(tasks.value));
    const project = computed(() => projectSource.value ?? {});

    const sprintById = computed(() => {
        const m = new Map();
        sprints.value.forEach((s) => m.set(s.id, s));
        return m;
    });

    const tasksBySprint = computed(() => {
        const map = {};
        sprints.value.forEach((s) => { map[s.id] = []; });
        rootTasks.value.forEach((t) => {
            if (t.sprint_id && map[t.sprint_id]) map[t.sprint_id].push(t);
            else if (!t.sprint_id) {
                if (!map._backlog) map._backlog = [];
                map._backlog.push(t);
            }
        });
        return map;
    });

    const backlogTasks = computed(() => rootTasks.value.filter((t) => !t.sprint_id));

    const kpis = computed(() => {
        const allSprints = sprints.value;
        const allTasks = tasks.value;

        const activeSprints = allSprints.filter((s) => s.status?.value === 'active');
        const plannedSprints = allSprints.filter((s) => s.status?.value === 'planned');
        const completedSprints = allSprints.filter((s) => s.status?.value === 'completed');
        const overdueSprints = allSprints.filter(isSprintOverdue);

        const doneTasks = allTasks.filter(isTaskDone);
        const inProgressTasks = allTasks.filter((t) => ['in_progress', 'in_review'].includes(t.status?.value));
        const blockedTasks = allTasks.filter(isTaskBlocked);
        const overdueTasks = allTasks.filter(isTaskOverdue);

        const totalEstimate = allTasks.reduce((s, t) => s + (Number(t.estimate_hours) || 0), 0);
        const doneEstimate = doneTasks.reduce((s, t) => s + (Number(t.estimate_hours) || 0), 0);
        const velocity = activeSprints.length
            ? doneEstimate / activeSprints.length
            : doneEstimate;
        const capacity = activeSprints.reduce((s, sp) => {
            const st = tasksBySprint.value[sp.id] || [];
            return s + st.reduce((a, t) => a + (Number(t.estimate_hours) || 0), 0);
        }, 0);
        const burnRate = totalEstimate > 0 ? Math.round((doneEstimate / totalEstimate) * 100) : 0;
        const projectProgress = completionPercentFromTasks(rootTasks.value, isTaskDone);

        return {
            totalSprints: allSprints.length,
            activeSprints: activeSprints.length,
            plannedSprints: plannedSprints.length,
            completedSprints: completedSprints.length,
            overdueSprints: overdueSprints.length,
            totalTasks: allTasks.length,
            doneTasks: doneTasks.length,
            inProgressTasks: inProgressTasks.length,
            blockedTasks: blockedTasks.length,
            overdueTasks: overdueTasks.length,
            velocity: Math.round(velocity * 10) / 10,
            capacity: Math.round(capacity * 10) / 10,
            burnRate,
            projectProgress,
        };
    });

    const sprintMetrics = (sprintId) => {
        const st = tasksBySprint.value[sprintId] || [];
        const done = st.filter(isTaskDone);
        const est = st.reduce((a, t) => a + (Number(t.estimate_hours) || 0), 0);
        const actual = done.reduce((a, t) => a + (Number(t.actual_hours) || 0), 0);
        const doneEst = done.reduce((a, t) => a + (Number(t.estimate_hours) || 0), 0);
        const earlyCount = done.filter((t) => t.hours_timing?.value === 'early').length;
        const onPlanCount = done.filter((t) => t.hours_timing?.value === 'on_plan').length;
        const overSlaCount = done.filter((t) => t.sla_result?.value === 'exceeded').length;
        const slaEligible = done.filter((t) => t.sla_result?.value).length;
        const slaMetCount = done.filter((t) => t.sla_result?.value === 'met').length;
        const slaComplianceRate = slaEligible > 0
            ? Math.round((slaMetCount / slaEligible) * 100)
            : null;
        const members = new Set();
        st.forEach((t) => getAssignees(t).forEach((a) => members.add(a.id)));
        return {
            taskCount: st.length,
            doneCount: done.length,
            lateCount: st.filter(isTaskOverdue).length,
            progress: completionPercentFromTasks(st, isTaskDone),
            velocity: doneEst,
            capacity: est,
            totalActualHours: Math.round(actual * 10) / 10,
            earlyCount,
            onPlanCount,
            overSlaCount,
            slaComplianceRate,
            memberCount: members.size,
        };
    };

    return {
        sprints,
        tasks,
        rootTasks,
        project,
        sprintById,
        tasksBySprint,
        backlogTasks,
        kpis,
        sprintMetrics,
    };
}
