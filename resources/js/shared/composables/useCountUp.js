import { ref, toValue, watch, onUnmounted } from 'vue';

/**
 * Animate a numeric ref from its previous value up to a target using
 * requestAnimationFrame. Honours prefers-reduced-motion (jumps to the value).
 *
 * @param {import('vue').MaybeRefOrGetter<number|null|undefined>} target
 * @param {{ duration?: number, decimals?: number }} [options]
 * @returns {{ display: import('vue').Ref<number> }}
 */
export function useCountUp(target, options = {}) {
    const duration = options.duration ?? 900;
    const decimals = options.decimals ?? 0;
    const factor = 10 ** decimals;
    const display = ref(0);

    let raf = null;
    const reduced = typeof window !== 'undefined'
        && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    function round(v) {
        return Math.round(v * factor) / factor;
    }

    function animate(to) {
        if (raf) {
            cancelAnimationFrame(raf);
            raf = null;
        }
        const end = Number(to) || 0;
        if (reduced || duration <= 0) {
            display.value = round(end);
            return;
        }
        const from = display.value;
        const start = performance.now();

        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            // easeOutCubic
            const eased = 1 - (1 - t) ** 3;
            display.value = round(from + (end - from) * eased);
            if (t < 1) {
                raf = requestAnimationFrame(step);
            } else {
                raf = null;
            }
        };
        raf = requestAnimationFrame(step);
    }

    watch(
        () => toValue(target),
        (v) => animate(v),
        { immediate: true },
    );

    onUnmounted(() => {
        if (raf) cancelAnimationFrame(raf);
    });

    return { display };
}
