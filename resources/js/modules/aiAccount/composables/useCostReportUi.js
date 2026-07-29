import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    PROPOSAL_COLUMNS,
    PROPOSAL_COLUMNS_DEFAULT,
} from '@/modules/aiAccount/config/proposalColumns';

const PROPOSAL_COLS_KEY = 'va-workspace.ai-cost-report.proposal-columns';

function loadKeys(storageKey, defaults, allCols) {
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey));
        if (Array.isArray(saved) && saved.length) {
            return saved.filter((k) => allCols.some((c) => c.key === k));
        }
    } catch {
        /* ignore */
    }
    return [...defaults];
}

export function useCostReportUi(proposalsRef) {
    const proposalStatusTab = ref('all');
    const proposalExpanded = ref({});
    const groupExpanded = ref({});
    const detailExpanded = ref({});

    const proposalColumnKeys = ref(loadKeys(PROPOSAL_COLS_KEY, PROPOSAL_COLUMNS_DEFAULT, PROPOSAL_COLUMNS));

    watch(proposalColumnKeys, (v) => {
        localStorage.setItem(PROPOSAL_COLS_KEY, JSON.stringify(v));
    }, { deep: true });

    const showProposalColDd = ref(false);
    const proposalColDdRef = ref(null);

    const onDocClick = (e) => {
        if (proposalColDdRef.value && !proposalColDdRef.value.contains(e.target)) {
            showProposalColDd.value = false;
        }
    };
    onMounted(() => document.addEventListener('mousedown', onDocClick));
    onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

    const proposalColVisible = computed(() => {
        const set = new Set(proposalColumnKeys.value);
        return Object.fromEntries(PROPOSAL_COLUMNS.map((c) => [c.key, set.has(c.key)]));
    });

    function toggleProposalColumn(key) {
        const set = new Set(proposalColumnKeys.value);
        if (set.has(key)) set.delete(key);
        else set.add(key);
        proposalColumnKeys.value = [...set];
    }

    const filteredProposals = computed(() => {
        const list = proposalsRef.value ?? [];
        if (proposalStatusTab.value === 'all') return list;
        return list.filter((p) => p.status === proposalStatusTab.value);
    });

    const proposalGroups = computed(() => {
        const order = [];
        const map = new Map();
        for (const p of filteredProposals.value) {
            const key = p.group_function;
            if (!map.has(key)) {
                map.set(key, {
                    group: key,
                    group_label: p.group_label ?? key,
                    dot_color: p.group_dot_color ?? '#94a3b8',
                    items: [],
                });
                order.push(key);
            }
            map.get(key).items.push(p);
        }
        return order.map((k) => map.get(k));
    });

    function toggleProposalGroup(groupKey) {
        proposalExpanded.value[groupKey] = !proposalExpanded.value[groupKey];
    }

    function toggleDetail(id) {
        detailExpanded.value[id] = !detailExpanded.value[id];
    }

    function expandAllProposalGroups() {
        for (const g of proposalGroups.value) {
            proposalExpanded.value[g.group] = true;
        }
    }

    function collapseAllProposalGroups() {
        for (const g of proposalGroups.value) {
            proposalExpanded.value[g.group] = false;
        }
    }

    watch(proposalGroups, (groups) => {
        for (const g of groups) {
            if (proposalExpanded.value[g.group] === undefined) {
                proposalExpanded.value[g.group] = g.items.some((i) => i.status === 'pending');
            }
        }
    }, { immediate: true });

    return {
        proposalStatusTab,
        proposalExpanded,
        groupExpanded,
        detailExpanded,
        proposalColVisible,
        showProposalColDd,
        proposalColDdRef,
        proposalGroups,
        filteredProposals,
        toggleProposalColumn,
        toggleProposalGroup,
        toggleDetail,
        expandAllProposalGroups,
        collapseAllProposalGroups,
        PROPOSAL_COLUMNS,
    };
}
