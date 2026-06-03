import { computed, ref } from 'vue';

/**
 * @param {Array<{ key: string, label: string, default?: boolean }>} controls
 * @param {string} storageKey
 */
export function useVisibleFilterControls(controls, storageKey) {
    const defaultState = () =>
        Object.fromEntries(controls.map((c) => [c.key, c.default !== false]));

    function load() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey));
            if (saved && typeof saved === 'object') {
                return Object.fromEntries(
                    controls.map((c) => [c.key, saved[c.key] !== false]),
                );
            }
        } catch {
            /* ignore */
        }
        return defaultState();
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
