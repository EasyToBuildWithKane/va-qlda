import { ref } from 'vue';
import { httpGet, httpPatch, httpPost } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

export function useAiCostReport() {
    const toast = useToast();
    const loading = ref(false);
    const byGroup = ref([]);
    const totals = ref(null);
    const cards = ref(null);
    const proposals = ref([]);
    const proposalCounts = ref({
        total: 0, draft: 0, submitted: 0, pending: 0,
        approved: 0, rejected: 0, purchased: 0, active: 0, expired: 0,
    });

    async function load() {
        loading.value = true;
        try {
            const [summaryRes, proposalRes] = await Promise.all([
                httpGet(route('api.ai-accounts.summary')),
                httpGet(route('api.ai-accounts.proposals.index')),
            ]);

            const summary = summaryRes.data ?? summaryRes;
            byGroup.value = summary.by_group ?? [];
            totals.value = summary.totals ?? null;
            cards.value = summary.cards ?? null;

            const propData = proposalRes.data ?? {};
            proposals.value = propData.proposals ?? [];
            proposalCounts.value = {
                total: 0, draft: 0, submitted: 0, pending: 0,
                approved: 0, rejected: 0, purchased: 0, active: 0, expired: 0,
                ...(propData.counts ?? {}),
            };
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được dữ liệu.');
        } finally {
            loading.value = false;
        }
    }

    async function loadProposals(params = {}) {
        try {
            const res = await httpGet(route('api.ai-accounts.proposals.index'), { params });
            const data = res.data ?? {};
            proposals.value = data.proposals ?? [];
            proposalCounts.value = {
                total: 0, draft: 0, submitted: 0, pending: 0,
                approved: 0, rejected: 0, purchased: 0, active: 0, expired: 0,
                ...(data.counts ?? {}),
            };
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được phiếu đề xuất.');
        }
    }

    async function createProposal(payload) {
        const res = await httpPost(route('api.ai-accounts.proposals.store'), payload);
        toast.success(res.message ?? 'Đã gửi phiếu đề xuất.');
        await load();
        return res.data?.proposal;
    }

    async function approveProposal(id, review_notes = null) {
        const res = await httpPost(route('api.ai-accounts.proposals.approve', { proposal: id }), {
            review_notes: review_notes || null,
        });
        toast.success(res.message ?? 'Đã duyệt.');
        await load();
    }

    async function rejectProposal(id, rejection_reason) {
        const res = await httpPost(route('api.ai-accounts.proposals.reject', { proposal: id }), {
            rejection_reason,
        });
        toast.success(res.message ?? 'Đã từ chối.');
        await load();
    }

    async function updateProposalNotes(id, review_notes) {
        const res = await httpPatch(route('api.ai-accounts.proposals.notes', { proposal: id }), {
            review_notes,
        });
        toast.success(res.message ?? 'Đã lưu ghi chú.');
        await loadProposals();
    }

    return {
        loading,
        byGroup,
        totals,
        cards,
        proposals,
        proposalCounts,
        load,
        loadProposals,
        createProposal,
        approveProposal,
        rejectProposal,
        updateProposalNotes,
    };
}
