import { ref, computed, onMounted, onBeforeUnmount, unref } from 'vue';

const PHASE_HEIGHT = 36;
const TASK_HEIGHT = 44;
const MILESTONE_HEADER = 32;
const MILESTONE_ROW = 36;

export function rowHeightFor(item) {
    if (item.type === 'phase' || item.type === 'sprint') return PHASE_HEIGHT;
    if (item.type === 'milestone-header') return MILESTONE_HEADER;
    if (item.type === 'milestone') return MILESTONE_ROW;
    return TASK_HEIGHT;
}

/**
 * Windowed list for large timelines (5000+ rows friendly).
 */
export function useVirtualScroll(scrollRef, rowsSource) {
    const scrollTop = ref(0);
    const viewportHeight = ref(560);
    let resizeObserver = null;

    const layout = computed(() => {
        const rows = unref(rowsSource) || [];
        let y = 0;
        return rows.map((row) => {
            const h = rowHeightFor(row);
            const item = { row, y, h };
            y += h;
            return item;
        });
    });

    const totalHeight = computed(() => {
        const items = layout.value;
        if (!items.length) return 0;
        const last = items[items.length - 1];
        return last.y + last.h;
    });

    const visibleItems = computed(() => {
        const items = layout.value;
        if (!items.length) return [];
        const top = scrollTop.value;
        const bottom = top + viewportHeight.value;
        const buffer = 10;
        let start = 0;
        let end = items.length;

        for (let i = 0; i < items.length; i++) {
            if (items[i].y + items[i].h >= top && start === 0) {
                start = Math.max(0, i - buffer);
            }
            if (items[i].y > bottom) {
                end = Math.min(items.length, i + buffer);
                break;
            }
        }

        return items.slice(start, end);
    });

    const onScroll = (e) => {
        scrollTop.value = e.target.scrollTop;
    };

    const measure = () => {
        const el = unref(scrollRef);
        if (el) viewportHeight.value = el.clientHeight || 560;
    };

    onMounted(() => {
        const el = unref(scrollRef);
        if (!el) return;
        measure();
        el.addEventListener('scroll', onScroll, { passive: true });
        resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(el);
    });

    onBeforeUnmount(() => {
        const el = unref(scrollRef);
        if (el) el.removeEventListener('scroll', onScroll);
        resizeObserver?.disconnect();
    });

    return { totalHeight, visibleItems, scrollTop, measure };
}
