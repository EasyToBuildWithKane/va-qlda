import { computed, ref, watch } from 'vue';

export const DEFAULT_PER_PAGE_OPTIONS = [5, 10, 15, 20];

function loadPerPage(storageKey, fallback = 10) {
    if (!storageKey) return fallback;
    try {
        const n = Number(localStorage.getItem(storageKey));
        if (DEFAULT_PER_PAGE_OPTIONS.includes(n)) return n;
    } catch {
        /* ignore */
    }
    return fallback;
}

/** @returns {number[]} Page numbers with gaps collapsed (ellipsis added separately). */
export function clientPaginationPageNumbers(current, last, onEachSide = 2) {
    if (last <= 0) return [];
    if (last === 1) return [1];

    const left = Math.max(2, current - onEachSide);
    const right = Math.min(last - 1, current + onEachSide);
    const pages = [1];

    if (left > 2) {
        pages.push(null);
    } else {
        for (let i = 2; i < left; i++) pages.push(i);
    }

    for (let i = left; i <= right; i++) {
        pages.push(i);
    }

    if (right < last - 1) {
        pages.push(null);
    } else {
        for (let i = right + 1; i < last; i++) pages.push(i);
    }

    if (last > 1) {
        pages.push(last);
    }

    return pages;
}

/**
 * @param {import('vue').Ref<Array>|import('vue').ComputedRef<Array>} itemsRef
 * @param {string} [storageKey]
 */
export function buildClientPaginationLinks(current, last, onEachSide = 2) {
    const links = [];
    links.push({
        label: '&laquo;',
        page: current > 1 ? current - 1 : null,
        active: false,
    });

    for (const entry of clientPaginationPageNumbers(current, last, onEachSide)) {
        if (entry === null) {
            links.push({ label: '…', page: null, active: false });
            continue;
        }
        links.push({
            label: String(entry),
            page: entry,
            active: entry === current,
        });
    }

    links.push({
        label: '&raquo;',
        page: current < last ? current + 1 : null,
        active: false,
    });

    return links;
}

export function useClientPagination(itemsRef, storageKey = null, defaultPerPage = 10) {
    const perPage = ref(loadPerPage(storageKey, defaultPerPage));
    const page = ref(1);

    watch([itemsRef, perPage], () => {
        page.value = 1;
    });

    const total = computed(() => (itemsRef.value ?? []).length);

    const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value) || 1));

    watch(lastPage, (lp) => {
        if (page.value > lp) page.value = lp;
    });

    const paginatedItems = computed(() => {
        const items = itemsRef.value ?? [];
        const start = (page.value - 1) * perPage.value;
        return items.slice(start, start + perPage.value);
    });

    const meta = computed(() => {
        const t = total.value;
        if (!t) {
            return {
                total: 0,
                from: 0,
                to: 0,
                current_page: 1,
                last_page: 1,
                links: [],
            };
        }
        const current = Math.min(page.value, lastPage.value);
        const from = (current - 1) * perPage.value + 1;
        const to = Math.min(current * perPage.value, t);
        return {
            total: t,
            from,
            to,
            current_page: current,
            last_page: lastPage.value,
            links: buildClientPaginationLinks(current, lastPage.value),
        };
    });

    function setPerPage(n) {
        perPage.value = n;
        page.value = 1;
        if (storageKey) {
            localStorage.setItem(storageKey, String(n));
        }
    }

    function goToPage(p) {
        const target = Number(p);
        if (target >= 1 && target <= lastPage.value) {
            page.value = target;
        }
    }

    return {
        perPage,
        page,
        paginatedItems,
        meta,
        setPerPage,
        goToPage,
        PER_PAGE_OPTIONS: DEFAULT_PER_PAGE_OPTIONS,
    };
}
