import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Position a teleported panel under (or above) an anchor element using fixed coordinates.
 */
export function useFixedDropdownAnchor(getAnchorEl, isOpen, options = {}) {
    const panelStyle = ref({ visibility: 'hidden' });
    const openUp = ref(false);

    const width = options.width ?? 224;
    const zIndex = options.zIndex ?? 80;
    const gap = options.gap ?? 6;
    const maxHeight = options.maxHeight ?? 320;

    function position() {
        const el = typeof getAnchorEl === 'function' ? getAnchorEl() : getAnchorEl?.value;
        if (!el) return;

        const rect = el.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom - gap;
        const spaceAbove = rect.top - gap;
        openUp.value = spaceBelow < maxHeight && spaceAbove > spaceBelow;

        const margin = 8;

        function contentMinLeft() {
            const main = document.querySelector('main');
            if (main) {
                return Math.max(margin, main.getBoundingClientRect().left + margin);
            }
            const aside = document.querySelector('aside');
            if (aside) {
                return Math.max(margin, aside.getBoundingClientRect().right + margin);
            }
            return margin;
        }

        const minLeft = contentMinLeft();
        let left = rect.right - width;
        if (left < minLeft) {
            left = Math.max(minLeft, rect.left);
        }
        if (left + width > window.innerWidth - margin) {
            left = Math.max(minLeft, window.innerWidth - width - margin);
        }

        panelStyle.value = {
            position: 'fixed',
            left: `${left}px`,
            width: `${width}px`,
            zIndex,
            visibility: 'visible',
            ...(openUp.value
                ? { bottom: `${window.innerHeight - rect.top + gap}px` }
                : { top: `${rect.bottom + gap}px` }),
        };
    }

    let listening = false;

    function startListen() {
        if (listening) return;
        listening = true;
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
    }

    function stopListen() {
        if (!listening) return;
        listening = false;
        window.removeEventListener('scroll', position, true);
        window.removeEventListener('resize', position);
    }

    watch(
        isOpen,
        (open) => {
            if (open) {
                panelStyle.value = { visibility: 'hidden' };
                requestAnimationFrame(() => position());
                startListen();
            } else {
                stopListen();
            }
        },
        { flush: 'post' },
    );

    onBeforeUnmount(stopListen);

    return { panelStyle, openUp, reposition: position };
}
