import { router } from '@inertiajs/vue3';

/** Chỉ cập nhật feed — giữ sidebar/aside tĩnh, tránh reload toàn trang. */
export const KB_BLOG_PARTIAL_PROPS = ['articles', 'filters'];

/**
 * @param {Record<string, unknown>} params Query blog (q, category_id, tag, per_page, page)
 * @param {import('@inertiajs/vue3').VisitOptions} [options]
 */
export function visitKbBlogFeed(params, options = {}) {
    const cleaned = Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v != null),
    );

    router.get(route('knowledge-base.blog'), cleaned, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: KB_BLOG_PARTIAL_PROPS,
        ...options,
    });
}
