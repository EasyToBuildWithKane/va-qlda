import axios from 'axios';
import { ref } from 'vue';

export function useAiExecutiveDashboard() {
    const loading = ref(false);
    const error = ref(null);
    const data = ref(null);
    const granularity = ref('month');
    const comparePreviousYear = ref(true);

    async function load() {
        loading.value = true;
        error.value = null;
        try {
            const { data: res } = await axios.get(route('api.ai-accounts.analytics.dashboard'), {
                params: {
                    granularity: granularity.value,
                    compare_previous_year: comparePreviousYear.value ? 1 : 0,
                },
            });
            data.value = res.data ?? null;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Không tải được dashboard.';
            data.value = null;
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        error,
        data,
        granularity,
        comparePreviousYear,
        load,
    };
}
