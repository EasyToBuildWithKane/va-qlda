import { computed, ref } from 'vue';
import { exportRiskBlockers } from '@/composables/useRiskExport';

export const RISK_SEVERITY_STYLE = {
    critical: 'bg-rose-100 text-rose-800 ring-rose-200/80 dark:bg-rose-950/50 dark:text-rose-300 dark:ring-rose-800/60',
    high: 'bg-orange-100 text-orange-800 ring-orange-200/80 dark:bg-orange-950/50 dark:text-orange-300 dark:ring-orange-800/60',
    medium: 'bg-amber-100 text-amber-800 ring-amber-200/80 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-800/60',
    low: 'bg-emerald-100 text-emerald-800 ring-emerald-200/80 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-800/60',
};

export const RISK_STATUS_STYLE = {
    open: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-800/50',
    in_progress: 'bg-sky-50 text-sky-700 ring-sky-200/70 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-800/50',
    blocked: 'bg-violet-50 text-violet-700 ring-violet-200/70 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-800/50',
    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-800/50',
    closed: 'bg-slate-100 text-slate-600 ring-slate-200/70 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700/50',
};

const TERMINAL = new Set(['resolved', 'closed']);
const ACTIVE = new Set(['open', 'in_progress', 'blocked']);

function rowSearchText(row) {
    return [
        row.code,
        row.title,
        row.description,
        row.root_cause,
        row.resolution,
        row.owner?.name,
        row.raised_by?.name,
        row.severity?.label,
        row.status?.label,
    ].filter(Boolean).join(' ').toLowerCase();
}

function sortValue(row, key) {
    switch (key) {
        case 'code': return row.code ?? '';
        case 'title': return row.title ?? '';
        case 'severity': {
            const o = { critical: 0, high: 1, medium: 2, low: 3 };
            return o[row.severity?.value] ?? 9;
        }
        case 'status': return row.status?.value ?? '';
        case 'owner': return row.owner?.name ?? '';
        case 'raised_by': return row.raised_by?.name ?? '';
        case 'raised_at': return row.raised_at ? new Date(row.raised_at).getTime() : 0;
        case 'due_date': return row.due_date ? new Date(`${row.due_date}T00:00:00`).getTime() : 0;
        case 'updated_at': return row.updated_at ? new Date(row.updated_at).getTime() : 0;
        default: return '';
    }
}

export function useRiskTable(blockersSource) {
    const search = ref('');
    const filterStatus = ref('all');
    const filterSeverity = ref('all');
    const filterOwner = ref('all');
    const sort = ref({ key: 'raised_at', dir: 'desc' });
    const page = ref(1);
    const pageSize = ref(10);
    const expanded = ref(new Set());
    const selected = ref(new Set());

    const rows = computed(() => blockersSource.value ?? []);

    const kpis = computed(() => {
        const all = rows.value;
        return {
            total: all.length,
            open: all.filter((r) => r.status?.value === 'open').length,
            inProgress: all.filter((r) => r.status?.value === 'in_progress' || r.status?.value === 'blocked').length,
            resolved: all.filter((r) => TERMINAL.has(r.status?.value)).length,
            overdue: all.filter((r) => r.is_overdue || (
                r.due_date
                && !TERMINAL.has(r.status?.value)
                && new Date(`${r.due_date}T00:00:00`) < new Date().setHours(0, 0, 0, 0)
            )).length,
        };
    });

    const ownerOptions = computed(() => {
        const map = new Map();
        rows.value.forEach((r) => {
            if (r.owner?.id) map.set(r.owner.id, r.owner.name);
        });
        return [...map.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'vi'));
    });

    const filtered = computed(() => {
        const q = search.value.trim().toLowerCase();
        return rows.value.filter((r) => {
            if (filterStatus.value !== 'all' && r.status?.value !== filterStatus.value) return false;
            if (filterSeverity.value !== 'all' && r.severity?.value !== filterSeverity.value) return false;
            if (filterOwner.value !== 'all') {
                const oid = filterOwner.value === 'none' ? null : Number(filterOwner.value);
                if ((r.owner?.id ?? null) !== oid) return false;
            }
            if (q && !rowSearchText(r).includes(q)) return false;
            return true;
        });
    });

    const sorted = computed(() => {
        const list = [...filtered.value];
        const { key, dir } = sort.value;
        if (!key) return list;
        const sign = dir === 'asc' ? 1 : -1;
        return list.sort((a, b) => {
            const va = sortValue(a, key);
            const vb = sortValue(b, key);
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * sign;
            return String(va).localeCompare(String(vb), 'vi') * sign;
        });
    });

    const pageCount = computed(() => Math.max(1, Math.ceil(sorted.value.length / pageSize.value)));

    const paginated = computed(() => {
        const p = Math.min(page.value, pageCount.value);
        const start = (p - 1) * pageSize.value;
        return sorted.value.slice(start, start + pageSize.value);
    });

    const toggleSort = (key) => {
        if (sort.value.key === key) sort.value.dir = sort.value.dir === 'asc' ? 'desc' : 'asc';
        else sort.value = { key, dir: 'asc' };
    };

    const toggleExpand = (id) => {
        const s = new Set(expanded.value);
        s.has(id) ? s.delete(id) : s.add(id);
        expanded.value = s;
    };

    const toggleSelect = (id) => {
        const s = new Set(selected.value);
        s.has(id) ? s.delete(id) : s.add(id);
        selected.value = s;
    };

    const toggleSelectAll = () => {
        const ids = paginated.value.map((r) => r.id);
        const allOnPage = ids.every((id) => selected.value.has(id));
        const s = new Set(selected.value);
        if (allOnPage) ids.forEach((id) => s.delete(id));
        else ids.forEach((id) => s.add(id));
        selected.value = s;
    };

    const clearSelection = () => { selected.value = new Set(); };

    const resetFilters = () => {
        search.value = '';
        filterStatus.value = 'all';
        filterSeverity.value = 'all';
        filterOwner.value = 'all';
        page.value = 1;
    };

    const exportRisk = (list, { projectCode = 'DA', projectName = '', format = 'xlsx' } = {}) => {
        exportRiskBlockers({ list, projectCode, projectName, format });
    };

    return {
        search,
        filterStatus,
        filterSeverity,
        filterOwner,
        sort,
        page,
        pageSize,
        pageCount,
        expanded,
        selected,
        kpis,
        ownerOptions,
        filtered,
        paginated,
        sorted,
        toggleSort,
        toggleExpand,
        toggleSelect,
        toggleSelectAll,
        clearSelection,
        resetFilters,
        exportRisk,
        TERMINAL,
        ACTIVE,
    };
}
