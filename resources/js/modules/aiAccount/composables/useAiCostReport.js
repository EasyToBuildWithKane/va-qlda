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
    const proposalCounts = ref({ pending: 0, approved: 0, rejected: 0 });

    async function load() {
        loading.value = true;
        try {
            const res = await httpGet(route('api.ai-accounts.summary'));
            const data = res.data ?? res;
            byGroup.value = data.by_group ?? [];
            totals.value = data.totals ?? null;
            cards.value = data.cards ?? null;
            proposals.value = data.proposals ?? [];
            proposalCounts.value = data.proposal_counts ?? { pending: 0, approved: 0, rejected: 0 };
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được báo cáo chi phí.');
        } finally {
            loading.value = false;
        }
    }

    async function createProposal(payload) {
        const res = await httpPost(route('api.ai-accounts.proposals.store'), payload);
        toast.success(res.message ?? 'Đã gửi đề xuất.');
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

    async function updateProposalNotes(id, review_notes) {
        const res = await httpPatch(route('api.ai-accounts.proposals.notes', { proposal: id }), {
            review_notes,
        });
        toast.success(res.message ?? 'Đã lưu ghi chú.');
        await load();
    }

    async function rejectProposal(id, rejection_reason) {
        const res = await httpPost(route('api.ai-accounts.proposals.reject', { proposal: id }), {
            rejection_reason,
        });
        toast.success(res.message ?? 'Đã từ chối.');
        await load();
    }

    return {
        loading,
        byGroup,
        totals,
        cards,
        proposals,
        proposalCounts,
        load,
        createProposal,
        approveProposal,
        rejectProposal,
        updateProposalNotes,
    };
}
