import { ref } from 'vue';
import { httpGet } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

export function useAiCostReport() {
    const toast = useToast();
    const loading = ref(false);
    const byGroup = ref([]);
    const totals = ref(null);
    const cards = ref(null);

    async function load() {
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

    return {
        loading,
        byGroup,
        totals,
        cards,
        load,
    };
}
