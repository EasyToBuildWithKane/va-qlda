import { computed, ref } from 'vue';

/**
 * @param {Array<{ key: string, label: string, default?: boolean }>} controls
 * @param {string} storageKey
 */
export function useVisibleFilterControls(controls, storageKey) {
    const defaultState = () =>
        Object.fromEntries(controls.map((c) => [c.key, c.default !== false]));

    function load() {
        const base = defaultState();
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return base;
            const saved = JSON.parse(raw);
            if (!saved || typeof saved !== 'object') return base;
            return Object.fromEntries(
                controls.map((c) => [
                    c.key,
                    Object.prototype.hasOwnProperty.call(saved, c.key)
                        ? saved[c.key] !== false
                        : base[c.key],
                ]),
            );
        } catch {
            /* ignore */
        }
        return base;
    }

    const visibleFilters = ref(load());
    const showFilterPanelDd = ref(false);

    const enabledFilterControlCount = computed(() =>
        controls.filter((c) => visibleFilters.value[c.key]).length,
    );

    const hasFilterRow = computed(() =>
        controls.some((c) => visibleFilters.value[c.key]),
    );

    function persistVisibleFilters() {
        localStorage.setItem(storageKey, JSON.stringify(visibleFilters.value));
    }

    function openFilterPanel(onOpen) {
        showFilterPanelDd.value = !showFilterPanelDd.value;
        if (showFilterPanelDd.value && onOpen) onOpen();
    }

    return {
        visibleFilters,
        showFilterPanelDd,
        enabledFilterControlCount,
        hasFilterRow,
        persistVisibleFilters,
        openFilterPanel,
        FILTER_CONTROLS: controls,
    };
}
