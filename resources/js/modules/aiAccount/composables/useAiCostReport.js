import { ref } from 'vue';
import { httpDelete, httpGet, httpPatch, httpPost, httpPut } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

export function useAiCostReport() {
    const toast = useToast();
    const loading = ref(false);
    const byGroup = ref([]);
    const totals = ref(null);
    const cards = ref(null);
    const proposals = ref([]);
    const proposalCounts = ref(emptyCounts());
    const proposalCountsFiltered = ref(emptyCounts());
    const workflowMetrics = ref(null);

    let cachedProposalParams = {};

    function emptyCounts() {
        return {
            total: 0, draft: 0, submitted: 0, pending: 0,
            approved: 0, rejected: 0, purchased: 0, active: 0, expired: 0,
        };
    }

    async function load(proposalParams) {
        const params = proposalParams !== undefined ? proposalParams : cachedProposalParams;
        cachedProposalParams = params;
        loading.value = true;
        try {
            const [summaryRes, proposalRes] = await Promise.all([
                httpGet(route('api.ai-accounts.summary')),
                httpGet(route('api.ai-accounts.proposals.index'), { params }),
            ]);

            const summary = summaryRes.data ?? summaryRes;
            byGroup.value = summary.by_group ?? [];
            totals.value = summary.totals ?? null;
            cards.value = summary.cards ?? null;

            const propData = proposalRes.data ?? {};
            proposals.value = propData.proposals ?? [];
            proposalCounts.value = { ...emptyCounts(), ...(propData.counts ?? {}) };
            proposalCountsFiltered.value = {
                ...emptyCounts(),
                ...(propData.filtered_counts ?? propData.counts ?? {}),
            };
            if (propData.workflow_metrics) {
                workflowMetrics.value = propData.workflow_metrics;
            } else if ((summaryRes.data ?? summaryRes).workflow_metrics) {
                workflowMetrics.value = (summaryRes.data ?? summaryRes).workflow_metrics;
            }
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được dữ liệu.');
        } finally {
            loading.value = false;
        }
    }

    async function loadSummary() {
        loading.value = true;
        try {
            const summaryRes = await httpGet(route('api.ai-accounts.summary'));
            const summary = summaryRes.data ?? summaryRes;
            byGroup.value = summary.by_group ?? [];
            totals.value = summary.totals ?? null;
            cards.value = summary.cards ?? null;
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được dữ liệu chi phí.');
        } finally {
            loading.value = false;
        }
    }

    async function loadProposals(params) {
        const resolved = params !== undefined ? params : cachedProposalParams;
        cachedProposalParams = resolved;
        try {
            const res = await httpGet(route('api.ai-accounts.proposals.index'), { params: resolved });
            const data = res.data ?? {};
            proposals.value = data.proposals ?? [];
            proposalCounts.value = { ...emptyCounts(), ...(data.counts ?? {}) };
            proposalCountsFiltered.value = {
                ...emptyCounts(),
                ...(data.filtered_counts ?? data.counts ?? {}),
            };
            if (data.workflow_metrics) {
                workflowMetrics.value = data.workflow_metrics;
            }
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được phiếu đề xuất.');
        }
    }

    async function reload() {
        await load(cachedProposalParams);
    }

    async function createProposal(payload) {
        const res = await httpPost(route('api.ai-accounts.proposals.store'), payload);
        toast.success(res.message ?? 'Đã gửi phiếu đề xuất.');
        await reload();
        return res.data?.proposal;
    }

    async function updateProposal(id, payload) {
        const res = await httpPut(route('api.ai-accounts.proposals.update', { proposal: id }), payload);
        toast.success(res.message ?? 'Đã cập nhật phiếu đề xuất.');
        await reload();
        return res.data?.proposal;
    }

    async function approveProposal(id, review_notes = null) {
        const res = await httpPost(route('api.ai-accounts.proposals.approve', { proposal: id }), {
            review_notes: review_notes || null,
        });
        toast.success(res.message ?? 'Đã duyệt.');
        await reload();
    }

    async function rejectProposal(id, rejection_reason) {
        const res = await httpPost(route('api.ai-accounts.proposals.reject', { proposal: id }), {
            rejection_reason,
        });
        toast.success(res.message ?? 'Đã từ chối.');
        await reload();
    }

    async function updateProposalNotes(id, review_notes) {
        const res = await httpPatch(route('api.ai-accounts.proposals.notes', { proposal: id }), {
            review_notes,
        });
        toast.success(res.message ?? 'Đã lưu ghi chú.');
        await reload();
    }

    async function deleteProposal(id) {
        const res = await httpDelete(route('api.ai-accounts.proposals.destroy', { proposal: id }));
        toast.success(res.message ?? 'Đã xoá phiếu.');
        await reload();
    }

    async function createPaymentRequest(proposalId, payload = {}) {
        const res = await httpPost(
            route('api.ai-accounts.proposals.payment-requests.store', { proposal: proposalId }),
            payload,
        );
        toast.success(res.message ?? 'Đã tạo đề nghị thanh toán.');
        // Cập nhật proposal trong list mà không reload toàn bộ
        const updated = res.data?.proposal;
        if (updated) {
            proposals.value = proposals.value.map((p) => (p.id === updated.id ? updated : p));
        }
        return updated;
    }

    async function approvePaymentRequest(prId, payload = {}) {
        const res = await httpPost(
            route('api.ai-accounts.payment-requests.approve', { paymentRequest: prId }),
            payload,
        );
        toast.success(res.message ?? 'Đã duyệt ĐNTT.');
        const updated = res.data?.proposal;
        if (updated) {
            proposals.value = proposals.value.map((p) => (p.id === updated.id ? updated : p));
        }
        return updated;
    }

    async function rejectPaymentRequest(prId, payload) {
        const res = await httpPost(
            route('api.ai-accounts.payment-requests.reject', { paymentRequest: prId }),
            payload,
        );
        toast.success(res.message ?? 'Đã từ chối ĐNTT.');
        const updated = res.data?.proposal;
        if (updated) {
            proposals.value = proposals.value.map((p) => (p.id === updated.id ? updated : p));
        }
        return updated;
    }

    async function markPaymentRequestPaid(prId, payload = {}) {
        const res = await httpPost(
            route('api.ai-accounts.payment-requests.mark-paid', { paymentRequest: prId }),
            payload,
        );
        toast.success(res.message ?? 'Đã ghi nhận thanh toán.');
        const updated = res.data?.proposal;
        if (updated) {
            proposals.value = proposals.value.map((p) => (p.id === updated.id ? updated : p));
        }
        return updated;
    }

    return {
        loading,
        byGroup,
        totals,
        cards,
        proposals,
        proposalCounts,
        proposalCountsFiltered,
        workflowMetrics,
        load,
        loadSummary,
        loadProposals,
        reload,
        createProposal,
        updateProposal,
        approveProposal,
        rejectProposal,
        updateProposalNotes,
        deleteProposal,
        createPaymentRequest,
        approvePaymentRequest,
        rejectPaymentRequest,
        markPaymentRequestPaid,
    };
}
