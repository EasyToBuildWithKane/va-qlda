import {
    computed, onBeforeUnmount, onMounted, ref, unref, watch,
} from 'vue';

const STORAGE_KEY = 'cn-assistant-pos-v2';
const DRAG_THRESHOLD = 6;
const NAV_TOP = 56;
const EDGE_PAD = 8;

/**
 * Vị trí trợ lý (fixed left/top) — kéo thả tự do, lưu localStorage, ẩn/hiện.
 */
export function useCongngheAssistantDock(asideRef, options = {}) {
    const posX = ref(0);
    const posY = ref(0);
    const positioned = ref(false);
    const isDragging = ref(false);
    const hidden = ref(false);

    let pointer = null;
    let moved = false;
    let captureEl = null;

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
        return {
            x: window.innerWidth - w - EDGE_PAD,
            y: window.innerHeight - h - EDGE_PAD,
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

    function detachWindowPointer() {
        if (typeof window === 'undefined') {
            return;
        }
        window.removeEventListener('pointermove', onWindowPointerMove);
        window.removeEventListener('pointerup', onWindowPointerUp);
        window.removeEventListener('pointercancel', onWindowPointerUp);
    }

    function onWindowPointerMove(e) {
        if (!pointer || e.pointerId !== pointer.id) {
            return;
        }
        const dx = e.clientX - pointer.startX;
        const dy = e.clientY - pointer.startY;
        if (!moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) {
            return;
        }
        if (!moved) {
            moved = true;
            isDragging.value = true;
        }
        applyPos(pointer.origX + dx, pointer.origY + dy);
        e.preventDefault();
    }

    function onWindowPointerUp(e) {
        if (!pointer || e.pointerId !== pointer.id) {
            return;
        }
        captureEl?.releasePointerCapture?.(e.pointerId);
        captureEl = null;
        const wasDrag = moved;
        pointer = null;
        isDragging.value = false;
        detachWindowPointer();
        if (wasDrag) {
            persist();
        } else if (typeof options.onTap === 'function') {
            options.onTap();
        }
        moved = false;
    }

    function onPointerDown(e) {
        if (e.button !== 0 && e.pointerType === 'mouse') {
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
        captureEl = e.currentTarget;
        captureEl.setPointerCapture?.(e.pointerId);
        window.addEventListener('pointermove', onWindowPointerMove, { passive: false });
        window.addEventListener('pointerup', onWindowPointerUp);
        window.addEventListener('pointercancel', onWindowPointerUp);
    }

    function onPointerMove() {
        /* legacy — dùng listener window */
    }

    function finishPointer(e) {
        if (!pointer) {
            return false;
        }
        if (e && e.pointerId !== pointer.id) {
            return moved;
        }
        const wasDrag = moved;
        onWindowPointerUp(e);
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
        detachWindowPointer();
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
