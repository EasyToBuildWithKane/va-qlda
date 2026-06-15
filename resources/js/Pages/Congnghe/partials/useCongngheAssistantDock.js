import {
    computed, onBeforeUnmount, onMounted, ref, unref, watch,
} from 'vue';

const STORAGE_KEY = 'cn-assistant-pos-v3';
const DRAG_THRESHOLD = 6;
const NAV_TOP = 56;
const EDGE_PAD = 8;

/**
 * Trợ lý cố định cạnh phải (gần thanh cuộn) — chỉ kéo dọc trên thanh rail.
 */
export function useCongngheAssistantDock(asideRef) {
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
        return { w: 220, h: 168 };
    }

    function defaultY() {
        if (typeof window === 'undefined') {
            return NAV_TOP + EDGE_PAD;
        }
        const { h } = widgetSize();
        return Math.max(NAV_TOP, window.innerHeight - h - EDGE_PAD);
    }

    function clampY(y) {
        if (typeof window === 'undefined') {
            return y;
        }
        const { h } = widgetSize();
        return Math.min(window.innerHeight - h - EDGE_PAD, Math.max(NAV_TOP, y));
    }

    function applyY(y) {
        posY.value = clampY(y);
        positioned.value = true;
    }

    function readStored() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                const data = JSON.parse(raw);
                if (data && typeof data === 'object') {
                    hidden.value = Boolean(data.hidden);
                    if (Number.isFinite(data.y)) {
                        applyY(data.y);
                        return;
                    }
                }
            }
            const legacy = localStorage.getItem('cn-assistant-pos-v2');
            if (legacy) {
                const data = JSON.parse(legacy);
                if (data && typeof data === 'object') {
                    hidden.value = Boolean(data.hidden);
                    if (Number.isFinite(data.y)) {
                        applyY(data.y);
                    }
                }
            }
        } catch {
            /* ignore */
        }
    }

    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                y: Math.round(posY.value),
                hidden: hidden.value,
            }));
        } catch {
            /* ignore */
        }
    }

    function ensurePosition() {
        if (!positioned.value) {
            applyY(defaultY());
        } else {
            applyY(posY.value);
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
        const dy = e.clientY - pointer.startY;
        if (!moved && Math.abs(dy) < DRAG_THRESHOLD) {
            return;
        }
        if (!moved) {
            moved = true;
            isDragging.value = true;
        }
        applyY(pointer.origY + dy);
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
        }
        moved = false;
    }

    function onRailPointerDown(e) {
        if (e.button !== 0 && e.pointerType === 'mouse') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        ensurePosition();
        pointer = {
            id: e.pointerId,
            startY: e.clientY,
            origY: posY.value,
        };
        moved = false;
        captureEl = e.currentTarget;
        captureEl.setPointerCapture?.(e.pointerId);
        window.addEventListener('pointermove', onWindowPointerMove, { passive: false });
        window.addEventListener('pointerup', onWindowPointerUp);
        window.addEventListener('pointercancel', onWindowPointerUp);
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
            top: `${posY.value}px`,
            right: 'max(0px, env(safe-area-inset-right))',
            left: 'auto',
            bottom: 'auto',
        };
    });

    return {
        posY,
        hidden,
        isDragging,
        dockStyle,
        onRailPointerDown,
        hideAssistant,
        showAssistant,
        ensurePosition,
    };
}
