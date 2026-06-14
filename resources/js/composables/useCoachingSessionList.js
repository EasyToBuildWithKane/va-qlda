import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { groupSessionsByCourse, formatMonthBounds } from '@/composables/coachingSessionDisplay';

const FILTER_KEYS = [
    'q', 'status', 'course', 'date_from', 'date_to', 'has_materials', 'has_assignments', 'scheduled',
];

/**
 * @param {{
 *   filters: object,
 *   getSessions: () => object,
 *   perPageDefault?: number,
 * }} opts
 */
export function useCoachingSessionList({ filters, getSessions, perPageDefault = 20 }) {
    const filterForm = reactive({
        q: filters.q ?? '',
        status: filters.status ?? '',
        course: filters.course ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        has_materials: filters.has_materials ?? '',
        has_assignments: filters.has_assignments ?? '',
        scheduled: filters.scheduled ?? '',
    });

    const perPage = ref(Number(filters.per_page) || getSessions().meta?.per_page || perPageDefault);
    const isNavigating = ref(false);

    const sessionRows = computed(() => getSessions().data ?? []);
    const groupedSessions = computed(() => groupSessionsByCourse(sessionRows.value));

    function routeParams(resetPage = false) {
        const params = Object.fromEntries(
            Object.entries({ ...filterForm, per_page: perPage.value }).filter(([, v]) => v !== '' && v != null),
        );
        if (resetPage) params.page = 1;
        return params;
    }

    function load(resetPage = true) {
        isNavigating.value = true;
        router.get(route('coaching.sessions.index'), routeParams(resetPage), {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onFinish: () => { isNavigating.value = false; },
        });
    }

    let qTimer = null;
    watch(() => filterForm.q, () => {
        clearTimeout(qTimer);
        qTimer = setTimeout(() => load(true), 350);
    });

    watch(
        () => FILTER_KEYS.filter((k) => k !== 'q').map((k) => filterForm[k]),
        () => load(true),
    );

    function onPerPageChange(n) {
        perPage.value = n;
        load(true);
    }

    const appliedFilterCount = computed(() => {
        let n = 0;
        FILTER_KEYS.filter((k) => k !== 'q').forEach((k) => {
            if (filterForm[k]) n += 1;
        });
        return n;
    });

    function clearFilters() {
        FILTER_KEYS.forEach((k) => { filterForm[k] = ''; });
        load(true);
    }

    function refreshList(onSuccessToast) {
        FILTER_KEYS.forEach((k) => { filterForm[k] = ''; });
        perPage.value = perPageDefault;
        router.get(route('coaching.sessions.index'), { per_page: perPageDefault }, {
            preserveScroll: true,
            replace: true,
            onSuccess: () => onSuccessToast?.(),
        });
    }

    function setStatusFilter(value) {
        filterForm.status = filterForm.status === value ? '' : value;
    }

    function applyMonthPreset(offsetMonths) {
        const { from, to } = formatMonthBounds(offsetMonths);
        filterForm.date_from = from;
        filterForm.date_to = to;
        filterForm.scheduled = '1';
    }

    function applyUnscheduledPreset() {
        filterForm.scheduled = '0';
        filterForm.date_from = '';
        filterForm.date_to = '';
    }

    async function fetchAllForExport() {
        const { data } = await axios.get(route('coaching.sessions.export'), {
            params: routeParams(false),
        });
        return data;
    }

    return {
        filterForm,
        perPage,
        sessionRows,
        groupedSessions,
        routeParams,
        load,
        onPerPageChange,
        appliedFilterCount,
        clearFilters,
        refreshList,
        setStatusFilter,
        applyMonthPreset,
        applyUnscheduledPreset,
        fetchAllForExport,
        isNavigating,
    };
}

export function useCoachingSessionGroups(storageKey) {
    function loadCollapsedGroups() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (raw) return new Set(JSON.parse(raw));
        } catch {
            /* ignore */
        }
        return new Set();
    }

    const collapsedGroups = ref(loadCollapsedGroups());

    function persistCollapsedGroups() {
        localStorage.setItem(storageKey, JSON.stringify([...collapsedGroups.value]));
    }

    function isGroupExpanded(key) {
        return !collapsedGroups.value.has(key);
    }

    function toggleGroup(key) {
        const next = new Set(collapsedGroups.value);
        if (next.has(key)) next.delete(key);
        else next.add(key);
        collapsedGroups.value = next;
        persistCollapsedGroups();
    }

    function toggleAllGroups(groupedSessions) {
        const allExpanded = groupedSessions.length > 0
            && groupedSessions.every((g) => isGroupExpanded(g.key));
        if (allExpanded) {
            collapsedGroups.value = new Set(groupedSessions.map((g) => g.key));
        } else {
            collapsedGroups.value = new Set();
        }
        persistCollapsedGroups();
    }

    const allGroupsExpanded = (groupedSessions) => (
        groupedSessions.length > 0
        && groupedSessions.every((g) => isGroupExpanded(g.key))
    );

    return {
        collapsedGroups,
        isGroupExpanded,
        toggleGroup,
        toggleAllGroups,
        allGroupsExpanded,
    };
}
