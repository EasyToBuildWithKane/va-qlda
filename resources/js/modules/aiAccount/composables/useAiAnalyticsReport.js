import axios from 'axios';
import { computed, ref } from 'vue';

const SAVED_FILTERS_KEY = 'va-qlda.ai-analytics.saved-filters';
const FAVORITE_FILTERS_KEY = 'va-qlda.ai-analytics.favorite-filters';

function readJson(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

export function useAiAnalyticsReport() {
    const loading = ref(false);
    const rows = ref([]);
    const stats = ref(null);
    const filterOptions = ref({ tools: [], vendors: [], departments: [], proposers: [] });

    const search = ref('');
    const department = ref('all');
    const groupFunction = ref('all');
    const tool = ref('all');
    const vendor = ref('all');
    const status = ref('all');
    const lifecycleStatus = ref('all');
    const proposalStatus = ref('all');
    const proposer = ref('all');
    const purchaseFrom = ref('');
    const purchaseTo = ref('');
    const expiryFrom = ref('');
    const expiryTo = ref('');
    const createdFrom = ref('');
    const createdTo = ref('');
    const costMin = ref('');
    const costMax = ref('');

    const savedFilters = ref(readJson(SAVED_FILTERS_KEY, []));
    const favoriteFilters = ref(readJson(FAVORITE_FILTERS_KEY, []));

    const filterPayload = computed(() => ({
        search: search.value.trim() || undefined,
        department: department.value,
        group_function: groupFunction.value,
        tool: tool.value,
        vendor: vendor.value,
        status: status.value,
        lifecycle_status: lifecycleStatus.value,
        proposal_status: proposalStatus.value,
        proposer: proposer.value,
        purchase_from: purchaseFrom.value || undefined,
        purchase_to: purchaseTo.value || undefined,
        expiry_from: expiryFrom.value || undefined,
        expiry_to: expiryTo.value || undefined,
        created_from: createdFrom.value || undefined,
        created_to: createdTo.value || undefined,
        cost_min: costMin.value !== '' ? Number(costMin.value) : undefined,
        cost_max: costMax.value !== '' ? Number(costMax.value) : undefined,
    }));

    async function loadFilterOptions() {
        const { data: res } = await axios.get(route('api.ai-accounts.analytics.filter-options'));
        filterOptions.value = res.data ?? filterOptions.value;
    }

    async function loadReport() {
        loading.value = true;
        try {
            const { data: res } = await axios.get(route('api.ai-accounts.analytics.report'), {
                params: filterPayload.value,
            });
            rows.value = res.data?.rows ?? [];
            stats.value = res.data?.stats ?? null;
        } finally {
            loading.value = false;
        }
    }

    function snapshotFilters(name) {
        const entry = {
            id: crypto.randomUUID(),
            name,
            created_at: new Date().toISOString(),
            values: { ...filterPayload.value },
        };
        savedFilters.value = [entry, ...savedFilters.value].slice(0, 20);
        localStorage.setItem(SAVED_FILTERS_KEY, JSON.stringify(savedFilters.value));
        return entry;
    }

    function applySnapshot(values) {
        search.value = values.search ?? '';
        department.value = values.department ?? 'all';
        groupFunction.value = values.group_function ?? 'all';
        tool.value = values.tool ?? 'all';
        vendor.value = values.vendor ?? 'all';
        status.value = values.status ?? 'all';
        lifecycleStatus.value = values.lifecycle_status ?? 'all';
        proposalStatus.value = values.proposal_status ?? 'all';
        proposer.value = values.proposer ?? 'all';
        purchaseFrom.value = values.purchase_from ?? '';
        purchaseTo.value = values.purchase_to ?? '';
        expiryFrom.value = values.expiry_from ?? '';
        expiryTo.value = values.expiry_to ?? '';
        createdFrom.value = values.created_from ?? '';
        createdTo.value = values.created_to ?? '';
        costMin.value = values.cost_min ?? '';
        costMax.value = values.cost_max ?? '';
    }

    function toggleFavorite(id) {
        const set = new Set(favoriteFilters.value);
        if (set.has(id)) {
            set.delete(id);
        } else {
            set.add(id);
        }
        favoriteFilters.value = [...set];
        localStorage.setItem(FAVORITE_FILTERS_KEY, JSON.stringify(favoriteFilters.value));
    }

    function shareFilterUrl() {
        const params = new URLSearchParams();
        Object.entries(filterPayload.value).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== '' && v !== 'all') {
                params.set(k, String(v));
            }
        });
        const url = `${window.location.origin}${window.location.pathname}?${params.toString()}`;
        return url;
    }

    function applyFromQuery(query) {
        const entries = query instanceof URLSearchParams
            ? Object.fromEntries(query.entries())
            : (query ?? {});
        if (!entries || typeof entries !== 'object') {
            return;
        }
        applySnapshot({
            search: entries.search,
            department: entries.department,
            group_function: entries.group_function,
            tool: entries.tool,
            vendor: entries.vendor,
            status: entries.status,
            lifecycle_status: entries.lifecycle_status,
            proposal_status: entries.proposal_status,
            proposer: entries.proposer,
            purchase_from: entries.purchase_from,
            purchase_to: entries.purchase_to,
            expiry_from: entries.expiry_from,
            expiry_to: entries.expiry_to,
            created_from: entries.created_from,
            created_to: entries.created_to,
            cost_min: entries.cost_min,
            cost_max: entries.cost_max,
        });
    }

    return {
        loading,
        rows,
        stats,
        filterOptions,
        search,
        department,
        groupFunction,
        tool,
        vendor,
        status,
        lifecycleStatus,
        proposalStatus,
        proposer,
        purchaseFrom,
        purchaseTo,
        expiryFrom,
        expiryTo,
        createdFrom,
        createdTo,
        costMin,
        costMax,
        savedFilters,
        favoriteFilters,
        filterPayload,
        loadFilterOptions,
        loadReport,
        snapshotFilters,
        applySnapshot,
        toggleFavorite,
        shareFilterUrl,
        applyFromQuery,
    };
}
