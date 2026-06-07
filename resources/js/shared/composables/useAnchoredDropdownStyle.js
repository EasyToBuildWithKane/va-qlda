import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/** Panel Teleport — click trong panel không được coi là click outside toolbar. */
export const ANCHORED_DROPDOWN_SELECTOR = '[data-va-anchored-dropdown]';

export function isAnchoredDropdownTarget(target) {
    return target instanceof Element && Boolean(target.closest(ANCHORED_DROPDOWN_SELECTOR));
}

/**
 * @param {import('vue').Ref<HTMLElement | null> | import('vue').Ref<{ value: HTMLElement | null }>} anchorRef
 * @param {import('vue').Ref<boolean>} showRef
 * @param {{ width?: number, gap?: number, zIndex?: number }} [opts]
 */
export function useAnchoredDropdownStyle(anchorRef, showRef, opts = {}) {
    const width = opts.width ?? 224;
    const gap = opts.gap ?? 6;
    const zIndex = opts.zIndex ?? 50;
    const panelStyle = ref({});

    function resolveAnchor() {
        const a = anchorRef?.value;
        if (!a) return null;
        if (a instanceof HTMLElement) return a;
        if (a.value instanceof HTMLElement) return a.value;
        return null;
    }

    async function position() {
        await nextTick();
        const el = resolveAnchor();
        if (!el) return;

        const rect = el.getBoundingClientRect();
        let left = rect.right - width;
        const pad = 12;
        left = Math.max(pad, Math.min(left, window.innerWidth - width - pad));

        const panelMaxH = 320;
        const spaceBelow = window.innerHeight - rect.bottom - gap;
        const spaceAbove = rect.top - gap;
        const openUp = spaceBelow < panelMaxH && spaceAbove > spaceBelow;

        panelStyle.value = {
            position: 'fixed',
            left: `${left}px`,
            width: `${width}px`,
            zIndex,
            ...(openUp
                ? { bottom: `${window.innerHeight - rect.top + gap}px` }
                : { top: `${rect.bottom + gap}px` }),
        };
    }

    function onReposition() {
        if (showRef.value) position();
    }

    watch(showRef, (open) => {
        if (open) {
            position();
            window.addEventListener('scroll', onReposition, true);
            window.addEventListener('resize', onReposition);
        } else {
            window.removeEventListener('scroll', onReposition, true);
            window.removeEventListener('resize', onReposition);
        }
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', onReposition, true);
        window.removeEventListener('resize', onReposition);
    });

    return { panelStyle, reposition: position };
}
