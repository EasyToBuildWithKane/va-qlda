import { ref, watch } from 'vue';
import { httpPost } from '@/shared/services/http';

const DEBOUNCE_MS = 400;

/**
 * Xem trước phiếu PDX — HTML từ cùng Blade partial với PDF export.
 *
 * @param {import('vue').Reactive<object>} form
 * @param {import('vue').Ref<string>} activeSection
 * @param {() => object} buildPayload
 */
export function useProposalPdfPreview(form, activeSection, buildPayload) {
    const html = ref('');
    const loading = ref(false);
    const error = ref(null);
    let timer = null;
    let requestId = 0;

    async function load() {
        const id = ++requestId;
        loading.value = true;
        error.value = null;
        try {
            const res = await httpPost(route('api.ai-accounts.proposals.preview'), buildPayload());
            if (id !== requestId) return;
            html.value = res?.data?.html ?? '';
        } catch {
            if (id !== requestId) return;
            error.value = 'Không tải được bản xem trước. Vui lòng thử lại.';
            html.value = '';
        } finally {
            if (id === requestId) loading.value = false;
        }
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(load, DEBOUNCE_MS);
    }

    watch(
        activeSection,
        (section) => {
            if (section === 'preview') schedule();
        },
    );

    watch(
        form,
        () => {
            if (activeSection.value === 'preview') schedule();
        },
        { deep: true },
    );

    function reset() {
        clearTimeout(timer);
        html.value = '';
        error.value = null;
        loading.value = false;
    }

    return { html, loading, error, refresh: load, reset };
}
