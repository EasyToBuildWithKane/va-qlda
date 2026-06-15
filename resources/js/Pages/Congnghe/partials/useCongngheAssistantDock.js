import {
    computed, onBeforeUnmount, onMounted, ref, unref, watch,
} from 'vue';
import { prefersReducedMotionNow } from './motion.js';

const STORAGE_KEY = 'cn-assistant-pos-v2';
const DRAG_THRESHOLD = 8;
const NAV_TOP = 56;
const EDGE_PAD = 8;

/**
 * Vị trí trợ lý (fixed left/top) — kéo thả tự do, lưu localStorage, ẩn/hiện.
 */
export function useCongngheAssistantDock(asideRef) {
    const posX = ref(0);
    const posY = ref(0);
    const positioned = ref(false);
    const isDragging = ref(false);
    const hidden = ref(false);

    let pointer = null;
    let moved = false;

    function widgetSize() {
        const el = unref(asideRef);
        if (el) {
            const r = el.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) {
                return { w: r.width, h: r.height };
            }
        }
        return { w: 200, h: 168 };
    }

    function defaultPos() {
        if (typeof window === 'undefined') {
            return { x: EDGE_PAD, y: NAV_TOP + EDGE_PAD };
        }
        const { w, h } = widgetSize();
        const safeR = 0;
        const safeB = 0;
        return {
            x: window.innerWidth - w - Math.max(EDGE_PAD, safeR),
            y: window.innerHeight - h - Math.max(EDGE_PAD, safeB),
        };
    }

    function clampPos(x, y) {
        if (typeof window === 'undefined') {
            return { x, y };
        }
        const { w, h } = widgetSize();
        return {
            x: Math.min(window.innerWidth - w - EDGE_PAD, Math.max(EDGE_PAD, x)),
            y: Math.min(window.innerHeight - h - EDGE_PAD, Math.max(NAV_TOP, y)),
        };
    }

    function applyPos(x, y) {
        const c = clampPos(x, y);
        posX.value = c.x;
        posY.value = c.y;
        positioned.value = true;
    }

    function readStored() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            const data = JSON.parse(raw);
            if (data && typeof data === 'object') {
                hidden.value = Boolean(data.hidden);
                if (Number.isFinite(data.x) && Number.isFinite(data.y)) {
                    applyPos(data.x, data.y);
                }
            }
        } catch {
            /* ignore */
        }
    }

    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                x: Math.round(posX.value),
                y: Math.round(posY.value),
                hidden: hidden.value,
            }));
        } catch {
            /* ignore */
        }
    }

    function ensurePosition() {
        if (!positioned.value) {
            const d = defaultPos();
            applyPos(d.x, d.y);
        } else {
            applyPos(posX.value, posY.value);
        }
    }

    function onResizeDock() {
        ensurePosition();
    }

    function canDrag() {
        return !prefersReducedMotionNow();
    }

    function onPointerDown(e) {
        if (!canDrag()) {
            return;
        }
        ensurePosition();
        pointer = {
            id: e.pointerId,
            startX: e.clientX,
            startY: e.clientY,
            origX: posX.value,
            origY: posY.value,
        };
        moved = false;
        e.currentTarget.setPointerCapture?.(e.pointerId);
    }

    function onPointerMove(e) {
        if (!pointer || e.pointerId !== pointer.id) {
            return;
        }
        const dx = e.clientX - pointer.startX;
        const dy = e.clientY - pointer.startY;
        if (!moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) {
            return;
        }
        moved = true;
        isDragging.value = true;
        applyPos(pointer.origX + dx, pointer.origY + dy);
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

    function hideAssistant() {
        hidden.value = true;
        persist();
    }

    function showAssistant() {
        hidden.value = false;
        persist();
        nextTickEnsure();
    }

    function nextTickEnsure() {
        if (typeof requestAnimationFrame !== 'undefined') {
            requestAnimationFrame(() => ensurePosition());
        } else {
            ensurePosition();
        }
    }

    onMounted(() => {
        readStored();
        nextTickEnsure();
        window.addEventListener('resize', onResizeDock, { passive: true });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('resize', onResizeDock);
    });

    watch(hidden, (isHidden) => {
        if (!isHidden) {
            nextTickEnsure();
        }
    });

    const dockStyle = computed(() => {
        if (!positioned.value && typeof window === 'undefined') {
            return {};
        }
        return {
            left: `${posX.value}px`,
            top: `${posY.value}px`,
            right: 'auto',
            bottom: 'auto',
        };
    });

    return {
        posX,
        posY,
        hidden,
        isDragging,
        dockStyle,
        onPointerDown,
        onPointerMove,
        finishPointer,
        hideAssistant,
        showAssistant,
        ensurePosition,
    };
}
