import { ref, watch, nextTick } from 'vue';

/** Khớp @page margin trong ai-purchase-proposal-styles (42mm + 15mm). */
export const PROPOSAL_PREVIEW_PAGE_HEIGHT_MM = 297;
export const PROPOSAL_PREVIEW_CONTENT_TOP_MM = 42;
export const PROPOSAL_PREVIEW_CONTENT_BOTTOM_MM = 15;

export function proposalPreviewContentHeightMm() {
    return PROPOSAL_PREVIEW_PAGE_HEIGHT_MM
        - PROPOSAL_PREVIEW_CONTENT_TOP_MM
        - PROPOSAL_PREVIEW_CONTENT_BOTTOM_MM;
}

function mmToPx(mm) {
    return (mm * 96) / 25.4;
}

/**
 * Đo số trang A4 sau khi HTML preview được gắn vào DOM.
 *
 * @param {import('vue').Ref<string>} htmlRef
 * @param {import('vue').Ref<HTMLElement|null>} hostRef — phần tử chứa v-html
 */
export function useProposalPreviewPageCount(htmlRef, hostRef) {
    const pageCount = ref(1);
    const contentHeightPx = ref(0);

    async function measure() {
        await nextTick();
        requestAnimationFrame(() => {
            const host = hostRef.value;
            if (!host || !htmlRef.value) {
                pageCount.value = 1;
                contentHeightPx.value = 0;
                return;
            }

            const content = host.querySelector('.doc-content-on-bg')
                ?? host.querySelector('.doc-content');
            if (!content) {
                pageCount.value = 1;
                contentHeightPx.value = 0;
                return;
            }

            const h = content.scrollHeight;
            contentHeightPx.value = h;
            const pageContentPx = mmToPx(proposalPreviewContentHeightMm());
            pageCount.value = Math.max(1, Math.ceil(h / pageContentPx));
        });
    }

    watch(htmlRef, measure);
    watch(hostRef, measure);

    return { pageCount, contentHeightPx, remeasure: measure };
}
