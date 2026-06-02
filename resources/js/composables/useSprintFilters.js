import { computed, ref } from 'vue';
import { FILTER_FIELD_DEFS } from '@/Components/Project/Sprint/sprintTableColumns';
import { normalizeList } from '@/composables/useNormalizeList';

const stripDiacritics = (x) =>
    String(x || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

export function getAssignees(t) {
    const raw = t.assignees?.length ? t.assignees : t.assignee ? [t.assignee] : [];
    return normalizeList(raw).filter((a) => a?.id != null);
}

function fieldType(key) {
    return FILTER_FIELD_DEFS.find((f) => f.key === key)?.type || 'text';
}

function getFieldValue(task, field, sprintsById) {
    switch (field) {
        case 'status': return task.status?.value ?? '';
        case 'priority': return task.priority?.value ?? '';
        case 'phase': return task.phase?.value ?? '';
        case 'assignee': return getAssignees(task).map((a) => String(a.id));
        case 'reviewer': return task.reviewer?.id ? String(task.reviewer.id) : '';
        case 'sprint': return task.sprint_id ? String(task.sprint_id) : '';
        case 'estimate_hours': return task.estimate_hours ?? null;
        case 'progress': return task.progress ?? 0;
        case 'due_date': return task.due_date ?? '';
        case 'start_date': return task.start_date ?? '';
        case 'title': return task.title ?? '';
        case 'is_milestone': return task.is_milestone ? '1' : '0';
        default: return '';
    }
}

function evalCondition(task, cond, ctx) {
    const { field, operator, value, valueTo } = cond;
    const raw = getFieldValue(task, field, ctx.sprintsById);
    const type = fieldType(field);

    if (operator === 'empty') {
        if (field === 'assignee') return getAssignees(task).length === 0;
        return raw === '' || raw === null || raw === undefined;
    }
    if (operator === 'not_empty') {
        if (field === 'assignee') return getAssignees(task).length > 0;
        return raw !== '' && raw !== null && raw !== undefined;
    }

    if (field === 'assignee' && Array.isArray(raw)) {
        const target = String(value ?? '');
        if (operator === 'eq') return raw.includes(target);
        if (operator === 'neq') return !raw.includes(target);
        return true;
    }

    if (type === 'number') {
        const n = Number(raw);
        const v = Number(value);
        const v2 = Number(valueTo);
        if (operator === 'eq') return n === v;
        if (operator === 'neq') return n !== v;
        if (operator === 'gt') return n > v;
        if (operator === 'lt') return n < v;
        if (operator === 'between') return n >= Math.min(v, v2) && n <= Math.max(v, v2);
        return true;
    }

    if (type === 'date') {
        const d = raw ? new Date(`${raw}T00:00:00`).getTime() : null;
        const v = value ? new Date(`${value}T00:00:00`).getTime() : null;
        const v2 = valueTo ? new Date(`${valueTo}T00:00:00`).getTime() : null;
        if (operator === 'eq') return d === v;
        if (operator === 'neq') return d !== v;
        if (operator === 'gt') return d != null && v != null && d > v;
        if (operator === 'lt') return d != null && v != null && d < v;
        if (operator === 'between') return d != null && v != null && v2 != null && d >= Math.min(v, v2) && d <= Math.max(v, v2);
        return true;
    }

    const s = String(raw ?? '').toLowerCase();
    const q = String(value ?? '').toLowerCase();
    if (operator === 'eq') return s === q;
    if (operator === 'neq') return s !== q;
    if (operator === 'contains') return s.includes(q);
    if (operator === 'not_contains') return !s.includes(q);
    return true;
}

function evalGroup(task, group, ctx) {
    const conditions = group.conditions?.filter((c) => c.field) || [];
    if (!conditions.length) return true;
    const results = conditions.map((c) => evalCondition(task, c, ctx));
    return group.logic === 'or' ? results.some(Boolean) : results.every(Boolean);
}

export function applyFilterTree(tasks, rootGroup, ctx = {}) {
    const groups = rootGroup?.groups?.length ? rootGroup.groups : [];
    if (!groups.length) return tasks;
    return tasks.filter((t) => {
        const groupResults = groups.map((g) => evalGroup(t, g, ctx));
        return rootGroup.logic === 'or' ? groupResults.some(Boolean) : groupResults.every(Boolean);
    });
}

export function conditionLabel(cond, enums = {}, sprints = [], employees = []) {
    const def = FILTER_FIELD_DEFS.find((f) => f.key === cond.field);
    const fieldLabel = def?.label || cond.field;
    const opLabels = {
        eq: '=', neq: '≠', contains: 'chứa', not_contains: 'không chứa',
        empty: 'trống', not_empty: 'có giá trị', gt: '>', lt: '<', between: 'giữa',
    };
    const op = opLabels[cond.operator] || cond.operator;

    let val = cond.value;
    if (cond.field === 'status') val = enums.taskStatus?.find((s) => s.value === cond.value)?.label || val;
    if (cond.field === 'priority') val = enums.taskPriority?.find((s) => s.value === cond.value)?.label || val;
    if (cond.field === 'sprint') {
        if (cond.operator === 'empty') val = 'Backlog';
        else val = sprints.find((s) => String(s.id) === String(cond.value))?.name || val;
    }
    if (cond.field === 'assignee' || cond.field === 'reviewer') {
        val = employees.find((e) => String(e.id) === String(cond.value))?.name || val;
    }
    if (['empty', 'not_empty'].includes(cond.operator)) return `${fieldLabel} ${op}`;
    if (cond.operator === 'between') return `${fieldLabel} ${op} ${cond.value} – ${cond.valueTo}`;
    return `${fieldLabel}: ${val || op}`;
}

export function useSprintFilters() {
    const filterRoot = ref({
        logic: 'and',
        groups: [
            { logic: 'and', conditions: [] },
        ],
    });

    const pinnedFilters = ref(['status', 'sprint', 'assignee']);

    const activeChips = computed(() => {
        const chips = [];
        filterRoot.value.groups?.forEach((g) => {
            g.conditions?.forEach((c) => {
                if (c.field && (c.operator === 'empty' || c.operator === 'not_empty' || c.value !== '' && c.value != null)) {
                    chips.push({ ...c, id: c.id || `${c.field}-${c.operator}-${c.value}` });
                }
            });
        });
        return chips;
    });

    const addCondition = (groupIndex = 0) => {
        const g = filterRoot.value.groups[groupIndex];
        if (!g) return;
        g.conditions.push({
            id: `c-${Date.now()}`,
            field: 'status',
            operator: 'eq',
            value: '',
            valueTo: '',
        });
    };

    const removeCondition = (groupIndex, condId) => {
        const g = filterRoot.value.groups[groupIndex];
        if (!g) return;
        g.conditions = g.conditions.filter((c) => c.id !== condId);
    };

    const addGroup = () => {
        filterRoot.value.groups.push({ logic: 'and', conditions: [] });
    };

    const clearFilters = () => {
        filterRoot.value = { logic: 'and', groups: [{ logic: 'and', conditions: [] }] };
    };

    const removeChip = (chip) => {
        filterRoot.value.groups.forEach((g) => {
            g.conditions = g.conditions.filter((c) => c.id !== chip.id);
        });
    };

    const globalSearch = ref('');

    const matchesSearch = (task, q) => {
        if (!q) return true;
        const hay = stripDiacritics([
            task.id,
            `TASK-${task.id}`,
            task.title,
            task.description,
            task.status?.label,
            task.priority?.label,
            task.phase?.label,
            getAssignees(task).map((a) => a.name).join(' '),
            task.reviewer?.name,
        ].join(' '));
        return hay.includes(stripDiacritics(q));
    };

    return {
        filterRoot,
        pinnedFilters,
        activeChips,
        globalSearch,
        addCondition,
        removeCondition,
        addGroup,
        clearFilters,
        removeChip,
        matchesSearch,
        applyFilterTree,
        conditionLabel,
    };
}
