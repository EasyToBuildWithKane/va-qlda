import { normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

/** Gần khớp Laravel `Str::slug()` cho tiêu đề tiếng Việt (preview client). */
export function slugifyTitle(title) {
    const base = normalizeSearchKey(title)
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    return base || 'bai-viet';
}
