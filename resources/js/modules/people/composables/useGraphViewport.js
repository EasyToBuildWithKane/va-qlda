import { ref, computed } from 'vue';

/**
 * Pan + zoom controller for the org graph canvas. Framework-agnostic geometry;
 * the component wires DOM events to these handlers and applies `transform`.
 */
export function useGraphViewport({ minScale = 0.3, maxScale = 2 } = {}) {
    const scale = ref(1);
    const tx = ref(0);
    const ty = ref(0);
    const isPanning = ref(false);

    /** @type {HTMLElement|null} */
    let viewportEl = null;
    let panStart = null;
    let content = { w: 0, h: 0 };

    const transform = computed(
        () => `translate(${tx.value}px, ${ty.value}px) scale(${scale.value})`,
    );

    function bindViewport(el) {
        viewportEl = el;
    }

    function vpSize() {
        if (!viewportEl) return { w: 0, h: 0 };
        return { w: viewportEl.clientWidth, h: viewportEl.clientHeight };
    }

    function clamp(s) {
        return Math.min(maxScale, Math.max(minScale, s));
    }

    function zoomAt(clientX, clientY, factor) {
        if (!viewportEl) return;
        const rect = viewportEl.getBoundingClientRect();
        const next = clamp(scale.value * factor);
        if (next === scale.value) return;
        const px = clientX - rect.left;
        const py = clientY - rect.top;
        const wx = (px - tx.value) / scale.value;
        const wy = (py - ty.value) / scale.value;
        scale.value = next;
        tx.value = px - wx * next;
        ty.value = py - wy * next;
    }

    function zoomCenter(factor) {
        const { w, h } = vpSize();
        if (!viewportEl) return;
        const rect = viewportEl.getBoundingClientRect();
        zoomAt(rect.left + w / 2, rect.top + h / 2, factor);
    }

    function onWheel(e) {
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
        zoomAt(e.clientX, e.clientY, factor);
    }

    function onPointerDown(e) {
        if (e.button !== 0) return;
        isPanning.value = true;
        panStart = { x: e.clientX, y: e.clientY, tx: tx.value, ty: ty.value };
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);
    }

    function onPointerMove(e) {
        if (!panStart) return;
        tx.value = panStart.tx + (e.clientX - panStart.x);
        ty.value = panStart.ty + (e.clientY - panStart.y);
    }

    function onPointerUp() {
        isPanning.value = false;
        panStart = null;
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', onPointerUp);
    }

    /** Fit content into the viewport; optional min scale (landing — chữ/thẻ lớn hơn, pan khi tràn). */
    function fitTo(contentW, contentH, { maxInitialScale = 1, minInitialScale = 0, padding = 0 } = {}) {
        content = { w: contentW, h: contentH };
        const { w, h } = vpSize();
        if (!contentW || !contentH || !w || !h) return;
        let s = Math.min(
            (w - padding) / contentW,
            (h - padding) / contentH,
            maxInitialScale,
        );
        if (minInitialScale > 0) {
            s = Math.max(s, minInitialScale);
        }
        s = clamp(s);
        scale.value = s;
        tx.value = (w - contentW * s) / 2;
        ty.value = (h - contentH * s) / 2;
    }

    /** Center a world-space point in the viewport at the current scale. */
    function centerOn(wx, wy) {
        const { w, h } = vpSize();
        if (!w || !h) return;
        tx.value = w / 2 - wx * scale.value;
        ty.value = h / 2 - wy * scale.value;
    }

    function reset() {
        fitTo(content.w, content.h);
    }

    return {
        scale,
        tx,
        ty,
        isPanning,
        transform,
        bindViewport,
        onWheel,
        onPointerDown,
        zoomIn: () => zoomCenter(1.2),
        zoomOut: () => zoomCenter(1 / 1.2),
        fitTo,
        centerOn,
        reset,
    };
}
