import { ref, watch, nextTick } from 'vue';
import {
    layoutProposalPreviewPages,
    highlightProposalPreviewPage,
} from '@/modules/aiAccount/composables/useProposalPreviewPaginatedLayout';

/**
 * @param {import('vue').Ref<string>} htmlRef
 * @param {import('vue').Ref<HTMLElement|null>} measureHostRef
 * @param {import('vue').Ref<HTMLElement|null>} pagesStackRef
 */
export function useProposalPreviewPageCount(htmlRef, measureHostRef, pagesStackRef, currentPageRef) {
    const pageCount = ref(1);

    async function remeasure() {
        await nextTick();
        requestAnimationFrame(() => {
            const n = layoutProposalPreviewPages(measureHostRef.value, pagesStackRef.value);
            pageCount.value = n;
            highlightProposalPreviewPage(
                pagesStackRef.value,
                currentPageRef?.value ?? 1,
            );
        });
    }

    watch(htmlRef, remeasure);
    watch(measureHostRef, remeasure);
    watch(pagesStackRef, remeasure);

    return { pageCount, remeasure };
}
