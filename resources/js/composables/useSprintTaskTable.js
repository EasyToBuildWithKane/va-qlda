import { computed, ref, watch } from 'vue';
import { buildTaskDisplayRows, filterRootTasks, getTaskAssignees } from '@/composables/useTaskHierarchy';

function sortValue(row, key, sprintById, allTasks) {
    switch (key) {
        case 'id': return row.id;
        case 'title': return row.title ?? '';
        case 'status': return row.status?.value ?? '';
        case 'priority': {
            const o = { urgent: 0, high: 1, medium: 2, low: 3 };
            return o[row.priority?.value] ?? 9;
        }
        case 'phase': return row.phase?.value ?? '';
        case 'assignee': return getTaskAssignees(row, allTasks)[0]?.name ?? '';
        case 'reviewer': return row.reviewer?.name ?? '';
        case 'sprint': return sprintById.get(row.sprint_id)?.name ?? 'zzz';
        case 'estimate_hours': return row.estimate_hours ?? 0;
        case 'progress': return row.progress ?? 0;
        case 'start_date': return row.start_date ? new Date(`${row.start_date}T00:00:00`).getTime() : 0;
        case 'due_date': return row.due_date ? new Date(`${row.due_date}T00:00:00`).getTime() : 0;
        case 'logged_hours': return row.logged_hours ?? 0;
        default: return '';
    }
}

export function useSprintTaskTable(tasksSource, options = {}) {
    const {
        globalSearch = ref(''),
        sprintById = computed(() => new Map()),
    } = options;

    const sort = ref({ key: 'due_date', dir: 'asc' });
    const page = ref(1);
    const pageSize = ref(25);
    const selected = ref(new Set());
    const expanded = ref(new Set());

    const rows = computed(() => tasksSource.value ?? []);

    const filtered = computed(() => {
        let list = rows.value;

        const q = globalSearch.value?.trim();
        if (q) {
            const lower = q.toLowerCase();
            const pool = rows.value;
            list = list.filter((t) => {
                const hay = [
                    t.id, `TASK-${t.id}`, t.title, t.description,
                    t.status?.label, t.priority?.label,
                    getTaskAssignees(t, pool).map((a) => a.name).join(' '),
                ].join(' ').toLowerCase();
                return hay.includes(lower);
            });
        }

        return list;
    });

    const sortedRoots = computed(() => {
        const list = [...filterRootTasks(filtered.value)];
        const { key, dir } = sort.value;
        if (!key) return list;
        const sign = dir === 'asc' ? 1 : -1;
        const map = sprintById.value;
        const pool = rows.value;
        return list.sort((a, b) => {
            const va = sortValue(a, key, map, pool);
            const vb = sortValue(b, key, map, pool);
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * sign;
            return String(va).localeCompare(String(vb), 'vi') * sign;
        });
    });

    const displayRows = computed(() => buildTaskDisplayRows(sortedRoots.value, rows.value, { includeSubtasks: false }));

    const pageCount = computed(() => Math.max(1, Math.ceil(displayRows.value.length / pageSize.value)));

    const paginated = computed(() => {
        const p = Math.min(page.value, pageCount.value);
        const start = (p - 1) * pageSize.value;
        return displayRows.value.slice(start, start + pageSize.value);
    });

    watch([filtered, pageSize], () => { page.value = 1; });

    const toggleSort = (key) => {
        if (sort.value.key === key) {
            sort.value = { key, dir: sort.value.dir === 'asc' ? 'desc' : 'asc' };
        } else {
            sort.value = { key, dir: 'asc' };
        }
    };

    const toggleSelect = (id) => {
        const s = new Set(selected.value);
        if (s.has(id)) s.delete(id);
        else s.add(id);
        selected.value = s;
    };

    const toggleSelectAll = (ids) => {
        const allSelected = ids.every((id) => selected.value.has(id));
        if (allSelected) {
            const s = new Set(selected.value);
            ids.forEach((id) => s.delete(id));
            selected.value = s;
        } else {
            selected.value = new Set([...selected.value, ...ids]);
        }
    };

    const clearSelection = () => { selected.value = new Set(); };

    return {
        sort,
        page,
        pageSize,
        selected,
        expanded,
        filtered,
        sortedRoots,
        displayRows,
        paginated,
        pageCount,
        toggleSort,
        toggleSelect,
        toggleSelectAll,
        clearSelection,
    };
}
