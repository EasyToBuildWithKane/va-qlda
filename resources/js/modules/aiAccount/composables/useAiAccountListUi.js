import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    AI_ACCOUNT_COLUMNS,
    AI_ACCOUNT_COLUMNS_DEFAULT,
    AI_ACCOUNT_STATUS_FILTER_OPTS,
} from '@/modules/aiAccount/config/columns';

const COLS_KEY = 'va-qlda.ai-accounts.columns';
const FILTER_KEY = 'va-qlda.ai-accounts.filters';

function loadVisibleColumns() {
    try {
        const saved = JSON.parse(localStorage.getItem(COLS_KEY));
        if (Array.isArray(saved) && saved.length) {
            return saved.filter((k) => AI_ACCOUNT_COLUMNS.some((c) => c.key === k));
        }
    } catch {
        /* ignore */
    }
    return [...AI_ACCOUNT_COLUMNS_DEFAULT];
}

function loadSavedFilters() {
    try {
        const saved = JSON.parse(localStorage.getItem(FILTER_KEY));
        if (saved && typeof saved === 'object') {
            return saved;
        }
    } catch {
        /* ignore */
    }
    return null;
}

/**
 * Bộ lọc client-side + chọn cột (pattern Department / Projects).
 */
export function useAiAccountListUi(groupsRef, optionsRef) {
    const saved = loadSavedFilters();

    const filters = reactive({
        status: saved?.status ?? 'all',
        groups: saved?.groups ?? [],
        attentionOnly: saved?.attentionOnly ?? false,
    });

    const visibleColumnKeys = ref(loadVisibleColumns());
    watch(visibleColumnKeys, (v) => {
        localStorage.setItem(COLS_KEY, JSON.stringify(v));
    }, { deep: true });

    watch(filters, () => {
        localStorage.setItem(FILTER_KEY, JSON.stringify({
            status: filters.status,
            groups: [...filters.groups],
            attentionOnly: filters.attentionOnly,
        }));
    }, { deep: true });

    const showFilterDd = ref(false);
    const showColDd = ref(false);
    const filterDdRef = ref(null);
    const colDdRef = ref(null);

    const onDocClick = (e) => {
        if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterDd.value = false;
        if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
    };
    onMounted(() => document.addEventListener('mousedown', onDocClick));
    onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

    const openFilter = () => {
        showFilterDd.value = !showFilterDd.value;
        showColDd.value = false;
    };
    const openCol = () => {
        showColDd.value = !showColDd.value;
        showFilterDd.value = false;
    };

    const activeFilterCount = computed(() => {
        let n = 0;
        if (filters.status !== 'all') n++;
        if (filters.groups.length > 0) n++;
        if (filters.attentionOnly) n++;
        return n;
    });

    const colVisible = computed(() => {
        const set = new Set(visibleColumnKeys.value);
        return Object.fromEntries(AI_ACCOUNT_COLUMNS.map((c) => [c.key, set.has(c.key)]));
    });

    function toggleColumn(key) {
        const set = new Set(visibleColumnKeys.value);
        if (set.has(key)) {
            if (set.size <= 1) return;
            set.delete(key);
        } else {
            set.add(key);
        }
        visibleColumnKeys.value = AI_ACCOUNT_COLUMNS.filter((c) => set.has(c.key)).map((c) => c.key);
    }

    function accountMatchesFilters(row) {
        if (filters.status !== 'all' && row.status !== filters.status) return false;
        if (filters.groups.length > 0 && !filters.groups.includes(row.group_function)) return false;
        if (filters.attentionOnly && !['expiring_soon', 'expired'].includes(row.status)) return false;
        return true;
    }

    const displayGroups = computed(() => {
        const source = groupsRef.value ?? [];
        return source
            .map((g) => {
                const accounts = (g.accounts ?? []).filter(accountMatchesFilters);
                if (accounts.length === 0) return null;
                const monthly = accounts.reduce((sum, a) => sum + (a.cost_monthly ?? 0), 0);
                const warningCount = accounts.filter((a) =>
                    ['expiring_soon', 'expired'].includes(a.status),
                ).length;
                return {
                    ...g,
                    accounts,
                    total: accounts.length,
                    total_cost_monthly: monthly,
                    has_warning: warningCount > 0,
                    warning_count: warningCount,
                };
            })
            .filter(Boolean);
    });

    const filteredAccountCount = computed(() =>
        displayGroups.value.reduce((n, g) => n + (g.accounts?.length ?? 0), 0),
    );

    const statusCounts = computed(() => {
        const all = (groupsRef.value ?? []).flatMap((g) => g.accounts ?? []);
        const counts = { all: all.length };
        for (const opt of AI_ACCOUNT_STATUS_FILTER_OPTS) {
            if (opt.key === 'all') continue;
            counts[opt.key] = all.filter((a) => a.status === opt.key).length;
        }
        return counts;
    });

    const groupFilterOptions = computed(() => optionsRef?.value?.group_function ?? []);

    function clearFilters() {
        filters.status = 'all';
        filters.groups = [];
        filters.attentionOnly = false;
    }

    function toggleGroupFilter(value) {
        const set = new Set(filters.groups);
        if (set.has(value)) set.delete(value);
        else set.add(value);
        filters.groups = [...set];
    }

    return {
        filters,
        activeFilterCount,
        displayGroups,
        filteredAccountCount,
        statusCounts,
        groupFilterOptions,
        clearFilters,
        toggleGroupFilter,
        AI_ACCOUNT_COLUMNS,
        AI_ACCOUNT_STATUS_FILTER_OPTS,
        colVisible,
        visibleColumnKeys,
        toggleColumn,
        showFilterDd,
        showColDd,
        filterDdRef,
        colDdRef,
        openFilter,
        openCol,
    };
}
