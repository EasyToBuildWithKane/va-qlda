import { onBeforeUnmount, onMounted, ref } from 'vue';
import { prefersReducedMotionNow } from './motion.js';

const STORAGE_KEY = 'cn-assistant-bottom-px';
const DRAG_THRESHOLD = 8;

/**
 * Vị trí dọc trợ lý (px từ đáy viewport) — mobile/tablet kéo thả, desktop cố định.
 */
export function useCongngheAssistantDock() {
    const bottomPx = ref(16);
    const isDragging = ref(false);
    const dockEnabled = ref(false);

    let pointer = null;
    let moved = false;

    function readStored() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const n = Number(raw);
            if (Number.isFinite(n) && n >= 0) {
                bottomPx.value = n;
            }
        } catch {
            /* ignore */
        }
    }

    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, String(Math.round(bottomPx.value)));
        } catch {
            /* ignore */
        }
    }

    function clampBottom(value) {
        if (typeof window === 'undefined') {
            return value;
        }
        const safeBottom = 8;
        const navClearance = 64;
        const widgetApprox = 140;
        const min = safeBottom;
        const max = Math.max(min, window.innerHeight - navClearance - widgetApprox);
        return Math.min(max, Math.max(min, value));
    }

    function syncEnabled() {
        dockEnabled.value = typeof window !== 'undefined'
            && window.matchMedia('(max-width: 1023px)').matches;
    }

    function onPointerDown(e) {
        if (!dockEnabled.value || prefersReducedMotionNow()) {
            return;
        }
        pointer = {
            id: e.pointerId,
            startY: e.clientY,
            startBottom: bottomPx.value,
        };
        moved = false;
        e.currentTarget.setPointerCapture?.(e.pointerId);
    }

    function onPointerMove(e) {
        if (!pointer || e.pointerId !== pointer.id) {
            return;
        }
        const dy = pointer.startY - e.clientY;
        if (!moved && Math.abs(dy) < DRAG_THRESHOLD) {
            return;
        }
        moved = true;
        isDragging.value = true;
        bottomPx.value = clampBottom(pointer.startBottom + dy);
    }

    function finishPointer(e) {
        if (!pointer || e.pointerId !== pointer.id) {
            return null;
        }
        e.currentTarget.releasePointerCapture?.(e.pointerId);
        const wasDrag = moved;
        pointer = null;
        isDragging.value = false;
        if (wasDrag) {
            persist();
        }
        moved = false;
        return wasDrag;
    }

    function onResizeDock() {
        syncEnabled();
        bottomPx.value = clampBottom(bottomPx.value);
    }

    onMounted(() => {
        readStored();
        bottomPx.value = clampBottom(bottomPx.value);
        syncEnabled();
        window.addEventListener('resize', onResizeDock, { passive: true });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('resize', onResizeDock);
    });

    const dockStyle = () => ({
        bottom: `max(${bottomPx.value}px, env(safe-area-inset-bottom, 0px))`,
    });

    return {
        bottomPx,
        isDragging,
        dockEnabled,
        dockStyle,
        onPointerDown,
        onPointerMove,
        finishPointer,
        clampBottom,
    };
}
