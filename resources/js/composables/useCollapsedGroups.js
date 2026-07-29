import { ref } from 'vue';

/**
 * Persist expand/collapse state for grouped lists (localStorage Set of keys).
 *
 * @param {string} storageKey
 */
export function useCollapsedGroups(storageKey) {
    function loadCollapsedGroups() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (raw) return new Set(JSON.parse(raw));
        } catch {
            /* ignore */
        }
        return new Set();
    }

    const collapsedGroups = ref(loadCollapsedGroups());

    function persistCollapsedGroups() {
        localStorage.setItem(storageKey, JSON.stringify([...collapsedGroups.value]));
    }

    function isGroupExpanded(key) {
        return !collapsedGroups.value.has(key);
    }

    function toggleGroup(key) {
        const next = new Set(collapsedGroups.value);
        if (next.has(key)) next.delete(key);
        else next.add(key);
        collapsedGroups.value = next;
        persistCollapsedGroups();
    }

    function toggleAllGroups(groupedItems) {
        const allExpanded = groupedItems.length > 0
            && groupedItems.every((g) => isGroupExpanded(g.key));
        if (allExpanded) {
            collapsedGroups.value = new Set(groupedItems.map((g) => g.key));
        } else {
            collapsedGroups.value = new Set();
        }
        persistCollapsedGroups();
    }

    const allGroupsExpanded = (groupedItems) => (
        groupedItems.length > 0
        && groupedItems.every((g) => isGroupExpanded(g.key))
    );

    return {
        collapsedGroups,
        isGroupExpanded,
        toggleGroup,
        toggleAllGroups,
        allGroupsExpanded,
    };
}
