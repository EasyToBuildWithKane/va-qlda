import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

/**
 * URL-bound filter state for list pages.
 * Syncs reactive filter object to URL query params via Inertia.
 *
 * @param {object} initial  Initial filter values (matches current URL params)
 * @param {object} options
 * @param {number} options.debounce  Debounce delay in ms (default 350)
 * @param {string} options.only      Inertia partial reload key (optional)
 */
export function useFilter(initial = {}, options = {}) {
    const { debounce = 350, only = null } = options;
    const filters = reactive({ ...initial });

    let timer = null;

    const apply = () => {
        const params = Object.fromEntries(
            Object.entries(filters).filter(([, v]) => v !== null && v !== '' && v !== undefined),
        );

        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...(only ? { only: [only] } : {}),
        });
    };

    const reset = () => {
        Object.keys(filters).forEach((k) => {
            filters[k] = Array.isArray(initial[k]) ? [] : null;
        });
    };

    watch(
        () => ({ ...filters }),
        () => {
            clearTimeout(timer);
            timer = setTimeout(apply, debounce);
        },
        { deep: true },
    );

    return { filters, apply, reset };
}
