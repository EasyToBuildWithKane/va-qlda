import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useClientPagination } from '@/shared/composables/useClientPagination';
import {
    AI_ACCOUNT_FILTER_CONTROLS,
    AI_ACCOUNT_STATUS_FILTER_OPTS,
    AI_ACCOUNT_TABLE_COLUMNS,
} from '@/modules/aiAccount/config/columns';

const FILTER_VALUES_KEY = 'va-qlda.ai-accounts.filters';
const VISIBLE_FILTERS_KEY = 'va-qlda.ai-accounts.filter-controls';
const COLS_KEY = 'va-qlda.ai-accounts.columns';
const PER_PAGE_KEY = 'va-qlda.ai-accounts.per-page';

function loadSavedFilters() {
    try {
        const saved = JSON.parse(localStorage.getItem(FILTER_VALUES_KEY));
        if (saved && typeof saved === 'object') {
            return saved;
        }
    } catch {
        /* ignore */
    }
    return null;
}

/**
 * Bộ lọc client-side + hiển thị cột/bộ lọc (pattern Department / CostReport).
 */
export function useAiAccountListUi(groupsRef, optionsRef) {
    const saved = loadSavedFilters();

    const filters = reactive({
        status: saved?.status ?? 'all',
        groups: saved?.groups ?? [],
        attentionOnly: saved?.attentionOnly ?? false,
    });

    watch(filters, () => {
        localStorage.setItem(FILTER_VALUES_KEY, JSON.stringify({
            status: filters.status,
            groups: [...filters.groups],
            attentionOnly: filters.attentionOnly,
        }));
    }, { deep: true });

    const {
        visibleFilters,
        showFilterPanelDd,
        enabledFilterControlCount,
        hasFilterRow,
        persistVisibleFilters,
        openFilterPanel,
        FILTER_CONTROLS,
    } = useVisibleFilterControls(AI_ACCOUNT_FILTER_CONTROLS, VISIBLE_FILTERS_KEY);

    const {
        visibleCols,
        showColDd,
        persistVisibleColumns,
        openColPanel,
        isColVisible,
    } = useVisibleColumns(AI_ACCOUNT_TABLE_COLUMNS, COLS_KEY);

    const filterDdRef = ref(null);
    const colDdRef = ref(null);

    const onDocClick = (e) => {
        if (filterDdRef.value && !filterDdRef.value.contains(e.target)) {
            showFilterPanelDd.value = false;
        }
        if (colDdRef.value && !colDdRef.value.contains(e.target)) {
            showColDd.value = false;
        }
    };
    onMounted(() => document.addEventListener('mousedown', onDocClick));
    onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

    const openFilter = () => {
        openFilterPanel(() => { showColDd.value = false; });
    };
    const openCol = () => {
        openColPanel(() => { showFilterPanelDd.value = false; });
    };

    const activeFilterCount = computed(() => {
        let n = 0;
        if (filters.status !== 'all') n++;
        if (filters.groups.length > 0) n++;
        if (filters.attentionOnly) n++;
        return n;
    });

    const colVisible = computed(() =>
        Object.fromEntries(AI_ACCOUNT_TABLE_COLUMNS.map((c) => [c.key, isColVisible(c.key)])),
    );

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

    const flatRows = computed(() =>
        displayGroups.value.flatMap((g) =>
            (g.accounts ?? []).map((account) => ({ account, group: g })),
        ),
    );

    const {
        perPage,
        paginatedItems: paginatedFlatRows,
        meta: paginationMeta,
        setPerPage,
        goToPage,
        PER_PAGE_OPTIONS,
    } = useClientPagination(flatRows, PER_PAGE_KEY, 10);

    const paginatedDisplayGroups = computed(() => {
        const map = new Map();
        for (const { account, group } of paginatedFlatRows.value) {
            const key = group.group;
            if (!map.has(key)) {
                map.set(key, { ...group, accounts: [] });
            }
            map.get(key).accounts.push(account);
        }
        return [...map.values()].map((g) => {
            const monthly = g.accounts.reduce((sum, a) => sum + (a.cost_monthly ?? 0), 0);
            const warningCount = g.accounts.filter((a) =>
                ['expiring_soon', 'expired'].includes(a.status),
            ).length;
            return {
                ...g,
                total: g.accounts.length,
                total_cost_monthly: monthly,
                has_warning: warningCount > 0,
                warning_count: warningCount,
            };
        });
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
        paginatedDisplayGroups,
        filteredAccountCount,
        perPage,
        paginationMeta,
        setPerPage,
        goToPage,
        PER_PAGE_OPTIONS,
        statusCounts,
        groupFilterOptions,
        clearFilters,
        toggleGroupFilter,
        AI_ACCOUNT_STATUS_FILTER_OPTS,
        AI_ACCOUNT_TABLE_COLUMNS,
        colVisible,
        visibleCols,
        persistVisibleColumns,
        visibleFilters,
        hasFilterRow,
        enabledFilterControlCount,
        persistVisibleFilters,
        FILTER_CONTROLS,
        showFilterPanelDd,
        showColDd,
        filterDdRef,
        colDdRef,
        openFilter,
        openCol,
    };
}
