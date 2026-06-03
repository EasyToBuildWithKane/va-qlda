import { computed, ref } from 'vue';

/**
 * @param {Array<{ key: string, label: string, default?: boolean }>} columns
 * @param {string} storageKey
 */
export function useVisibleColumns(columns, storageKey) {
    const defaultState = () =>
        Object.fromEntries(columns.map((c) => [c.key, c.default !== false]));

    function load() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey));
            if (saved && typeof saved === 'object') {
                return Object.fromEntries(
                    columns.map((c) => [c.key, saved[c.key] !== false]),
                );
            }
        } catch {
            /* ignore */
        }
        return defaultState();
    }

    const visibleCols = ref(load());
    const showColDd = ref(false);

    const visibleColumnCount = computed(() =>
        columns.filter((c) => visibleCols.value[c.key]).length,
    );

    function persistVisibleColumns() {
        localStorage.setItem(storageKey, JSON.stringify(visibleCols.value));
    }

    function openColPanel(onOpen) {
        showColDd.value = !showColDd.value;
        if (showColDd.value && onOpen) onOpen();
    }

    function isColVisible(key) {
        return visibleCols.value[key] === true;
    }

    return {
        visibleCols,
        showColDd,
        visibleColumnCount,
        persistVisibleColumns,
        openColPanel,
        isColVisible,
        TABLE_COLUMNS: columns,
    };
}
