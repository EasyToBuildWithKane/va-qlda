import { nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

/**
 * Theo dõi vùng scroll: hiện gợi ý fade trên/dưới khi còn nội dung ẩn.
 * @param {import('vue').WatchSource[]} refreshDeps — đổi layout (nav, collapse…) → đo lại
 * @param {import('vue').Ref<HTMLElement|null>} [externalScrollEl] — phần tử cuộn (nếu gán từ component con)
 */
export function useOverflowScrollHints(refreshDeps = [], externalScrollEl = null) {
    const scrollEl = externalScrollEl ?? ref(null);
    const edges = reactive({ top: false, bottom: false });

    const refresh = () => {
        const el = scrollEl.value;
        if (!el) {
            edges.top = false;
            edges.bottom = false;
            return;
        }
        const max = el.scrollHeight - el.clientHeight;
        const top = el.scrollTop;
        const pad = 8;
        edges.top = top > pad;
        edges.bottom = max > pad && top < max - pad;
    };

    let resizeObserver = null;

    function ensureResizeObserver() {
        if (resizeObserver) return resizeObserver;
        if (typeof ResizeObserver === 'undefined') return null;
        resizeObserver = new ResizeObserver(() => refresh());
        return resizeObserver;
    }

    const bindElement = (el) => {
        const ro = ensureResizeObserver();
        ro?.disconnect();
        if (el && ro) {
            ro.observe(el);
            refresh();
        }
    };

    onMounted(() => {
        ensureResizeObserver();
        bindElement(scrollEl.value);
        window.addEventListener('resize', refresh);
        nextTick(refresh);
    });

    watch(scrollEl, (el) => bindElement(el), { flush: 'post' });

    if (refreshDeps.length) {
        watch(refreshDeps, () => nextTick(refresh), { deep: true });
    }

    onUnmounted(() => {
        resizeObserver?.disconnect();
        resizeObserver = null;
        window.removeEventListener('resize', refresh);
    });

    return { scrollEl, edges, onScroll: refresh };
}
