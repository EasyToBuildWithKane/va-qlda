import { router } from '@inertiajs/vue3';

/**
 * @param {Record<string, unknown>} filters
 */
export function visitCredentialIndex(filters = {}) {
    router.get(route('credentials.index'), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
