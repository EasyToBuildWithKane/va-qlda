import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Template ref từ parent có thể là Ref, hoặc HTMLElement (Vue unwrap prop).
 */
export function resolveAnchorElement(source) {
    if (source == null) return null;
    if (typeof Element !== 'undefined' && source instanceof Element) return source;
    if (typeof source === 'object' && 'value' in source) {
        const inner = source.value;
        if (inner != null && typeof Element !== 'undefined' && inner instanceof Element) return inner;
    }
    return null;
}

function readAnchor(getAnchorEl) {
    const raw = typeof getAnchorEl === 'function' ? getAnchorEl() : getAnchorEl;
    return resolveAnchorElement(raw);
}

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

    function hiddenOffScreenStyle() {
        return {
            position: 'fixed',
            left: '-9999px',
            top: '0',
            width: `${width}px`,
            zIndex,
            visibility: 'hidden',
            pointerEvents: 'none',
        };
    }

    function position() {
        const el = readAnchor(getAnchorEl);
        if (!el) {
            panelStyle.value = hiddenOffScreenStyle();
            return false;
        }

        const rect = el.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom - gap;
        const spaceAbove = rect.top - gap;
        const preferDown = options.preferDown ?? false;

        openUp.value = spaceBelow < maxHeight && spaceAbove > spaceBelow;
        if (preferDown && spaceBelow >= Math.min(maxHeight, 160)) {
            openUp.value = false;
        }

        const availableHeight = Math.max(120, openUp.value ? spaceAbove : spaceBelow);
        const panelMaxHeight = Math.min(maxHeight, availableHeight);

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
            maxHeight: `${panelMaxHeight}px`,
            zIndex,
            visibility: 'visible',
            pointerEvents: 'auto',
            overflow: 'hidden',
            ...(openUp.value
                ? { bottom: `${window.innerHeight - rect.top + gap}px`, top: 'auto' }
                : { top: `${rect.bottom + gap}px`, bottom: 'auto' }),
        };
        return true;
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

    async function schedulePosition() {
        panelStyle.value = hiddenOffScreenStyle();
        await nextTick();
        const attempt = () => {
            if (position()) return;
            requestAnimationFrame(() => {
                if (!position()) {
                    requestAnimationFrame(() => position());
                }
            });
        };
        requestAnimationFrame(attempt);
    }

    watch(
        isOpen,
        (open) => {
            if (open) {
                schedulePosition();
                startListen();
            } else {
                stopListen();
                panelStyle.value = hiddenOffScreenStyle();
            }
        },
        { flush: 'post' },
    );

    onBeforeUnmount(stopListen);

    return { panelStyle, openUp, reposition: position };
}
