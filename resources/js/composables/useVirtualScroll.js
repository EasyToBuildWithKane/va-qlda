import { ref, computed, onMounted, onBeforeUnmount, unref } from 'vue';

const PHASE_HEIGHT = 36;
const TASK_MIN_HEIGHT = 44;
const MILESTONE_HEADER = 32;
const MILESTONE_MIN_ROW = 36;

const TASK_LINE_HEIGHT = 18;
const TASK_ROW_PADDING_Y = 8;

/** Cột tiêu đề trong grid task (khớp LIST_GRID_COLS ở ProjectTimelineView). */
export function estimateTaskTitleColumnWidth(listWidth, depth = 0) {
    const w = Math.max(200, listWidth);
    const assigneeCol = Math.max(128, w * 0.38);
    const daysCol = 76;
    const gaps = 16;
    const horizontalPad = 60;
    const title = w - horizontalPad - assigneeCol - daysCol - gaps - (depth ? 18 : 0);
    return Math.max(72, title);
}

function linesForText(text, columnWidthPx, charWidthPx = 6.8) {
    const t = String(text ?? '').trim();
    if (!t) return 1;
    const charsPerLine = Math.max(8, Math.floor(columnWidthPx / charWidthPx));
    return Math.ceil(t.length / charsPerLine);
}

export function rowHeightFor(item, listWidth = 320) {
    if (item.type === 'phase' || item.type === 'sprint') return PHASE_HEIGHT;
    if (item.type === 'milestone-header') return MILESTONE_HEADER;
    if (item.type === 'milestone') {
        const col = estimateTaskTitleColumnWidth(listWidth);
        const lines = linesForText(item.milestone?.title, col);
        const contentH = lines * TASK_LINE_HEIGHT + 4;
        return Math.max(MILESTONE_MIN_ROW, TASK_ROW_PADDING_Y + contentH);
    }
    if (item.type === 'task') {
        const depth = item.depth || 0;
        const col = estimateTaskTitleColumnWidth(listWidth, depth);
        const lines = linesForText(item.task?.title, col - (depth ? 14 : 0));
        const contentH = lines * TASK_LINE_HEIGHT + 6;
        return Math.max(TASK_MIN_HEIGHT, TASK_ROW_PADDING_Y + contentH);
    }
    return TASK_MIN_HEIGHT;
}

/**
 * Windowed list for large timelines (5000+ rows friendly).
 * @param {import('vue').Ref} scrollRef — panel cuộn dọc (timeline phải)
 * @param {import('vue').Ref|import('vue').ComputedRef} rowsSource
 * @param {import('vue').Ref|null} listWidthRef — panel trái (đo bề rộng cột tiêu đề)
 */
export function useVirtualScroll(scrollRef, rowsSource, listWidthRef = null) {
    const scrollTop = ref(0);
    const viewportHeight = ref(560);
    const listWidth = ref(320);
    let resizeObserver = null;

    const layout = computed(() => {
        const rows = unref(rowsSource) || [];
        const width = listWidth.value;
        let y = 0;
        return rows.map((row) => {
            const h = rowHeightFor(row, width);
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
        const widthEl = unref(listWidthRef);
        if (widthEl) listWidth.value = widthEl.clientWidth || 320;
    };

    onMounted(() => {
        const el = unref(scrollRef);
        if (!el) return;
        measure();
        el.addEventListener('scroll', onScroll, { passive: true });
        resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(el);
        const widthEl = unref(listWidthRef);
        if (widthEl) resizeObserver.observe(widthEl);
    });

    onBeforeUnmount(() => {
        const el = unref(scrollRef);
        if (el) el.removeEventListener('scroll', onScroll);
        resizeObserver?.disconnect();
    });

    return { totalHeight, visibleItems, scrollTop, measure };
}
