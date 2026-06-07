import { nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

/**
 * Theo dõi vùng scroll: hiện gợi ý fade trên/dưới khi còn nội dung ẩn.
 * @param {import('vue').WatchSource[]} refreshDeps — đổi layout (nav, collapse…) → đo lại
 */
export function useOverflowScrollHints(refreshDeps = []) {
    const scrollEl = ref(null);
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

    let resizeObserver;

    const bindElement = (el) => {
        resizeObserver?.disconnect();
        if (el) {
            resizeObserver.observe(el);
            refresh();
        }
    };

    onMounted(() => {
        resizeObserver = new ResizeObserver(() => refresh());
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
        window.removeEventListener('resize', refresh);
    });

    return { scrollEl, edges, onScroll: refresh };
}
