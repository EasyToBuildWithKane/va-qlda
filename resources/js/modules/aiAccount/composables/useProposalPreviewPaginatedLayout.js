/** Khớp lề in + vùng tránh thanh đỏ letterhead (background.png). */
export const PROPOSAL_PREVIEW_PAGE_WIDTH_MM = 210;
export const PROPOSAL_PREVIEW_PAGE_HEIGHT_MM = 297;
export const PROPOSAL_PREVIEW_MARGIN_TOP_MM = 42;
export const PROPOSAL_PREVIEW_MARGIN_RIGHT_MM = 12;
export const PROPOSAL_PREVIEW_MARGIN_BOTTOM_MM = 15;
export const PROPOSAL_PREVIEW_MARGIN_LEFT_MM = 14;
/** Thanh footer nền — nội dung không được tràn vào vùng này mỗi trang. */
export const PROPOSAL_PREVIEW_FOOTER_BAND_MM = 20;

export function proposalPreviewSliceHeightMm() {
    return PROPOSAL_PREVIEW_PAGE_HEIGHT_MM
        - PROPOSAL_PREVIEW_MARGIN_TOP_MM
        - PROPOSAL_PREVIEW_MARGIN_BOTTOM_MM
        - PROPOSAL_PREVIEW_FOOTER_BAND_MM;
}

export function mmToPx(mm) {
    return (mm * 96) / 25.4;
}

/**
 * Dựng stack trang A4 (mỗi trang một nền, clip nội dung — tránh cắt chữ bởi footer).
 *
 * @param {HTMLElement|null} measureHost — phần tử chứa HTML preview (v-html)
 * @param {HTMLElement|null} pagesStack — container nhận các tờ A4
 * @returns {number} số trang
 */
export function layoutProposalPreviewPages(measureHost, pagesStack) {
    if (!measureHost || !pagesStack) {
        pagesStack && (pagesStack.innerHTML = '');
        return 1;
    }

    const root = measureHost.querySelector('.proposal-preview-root');
    const source = root?.querySelector('.proposal-preview-flow');
    const bgUrl = root?.dataset?.backgroundUrl
        || root?.getAttribute('data-background-url')
        || '';

    pagesStack.innerHTML = '';

    if (!source || !bgUrl) {
        return 1;
    }

    const slicePx = mmToPx(proposalPreviewSliceHeightMm());
    const totalH = source.scrollHeight;
    const pageCount = Math.max(1, Math.ceil(totalH / slicePx));

    for (let i = 0; i < pageCount; i += 1) {
        const sheet = document.createElement('div');
        sheet.className = 'proposal-preview-page-sheet';
        sheet.dataset.page = String(i + 1);

        const badge = document.createElement('div');
        badge.className = 'proposal-preview-page-badge';
        badge.textContent = `Trang ${i + 1} / ${pageCount}`;

        const bg = document.createElement('img');
        bg.className = 'proposal-preview-page-bg';
        bg.src = bgUrl;
        bg.alt = '';
        bg.setAttribute('aria-hidden', 'true');
        bg.loading = 'eager';

        const clip = document.createElement('div');
        clip.className = 'proposal-preview-page-clip';

        const clone = source.cloneNode(true);
        clone.classList.add('proposal-preview-page-clone');
        clone.style.transform = `translateY(-${i * slicePx}px)`;

        clip.appendChild(clone);
        sheet.appendChild(bg);
        sheet.appendChild(clip);
        sheet.appendChild(badge);
        pagesStack.appendChild(sheet);
    }

    return pageCount;
}

/** @param {HTMLElement|null} pagesStack */
export function highlightProposalPreviewPage(pagesStack, page) {
    if (!pagesStack) return;
    pagesStack.querySelectorAll('.proposal-preview-page-sheet').forEach((el) => {
        el.classList.toggle(
            'proposal-preview-page-sheet--current',
            el.dataset.page === String(page),
        );
    });
}
