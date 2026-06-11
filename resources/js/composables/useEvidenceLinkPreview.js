import axios from 'axios';

const DIRECT_IMAGE_RE = /\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i;

const previewCache = new Map();

function directImageUrl(url) {
    const trimmed = (url ?? '').trim();
    if (!trimmed || !/^https:\/\//i.test(trimmed)) return null;
    if (DIRECT_IMAGE_RE.test(trimmed)) return trimmed;
    return null;
}

/**
 * @param {string} pageUrl
 * @returns {Promise<string|null>}
 */
export async function resolveEvidencePreviewImage(pageUrl) {
    const key = (pageUrl ?? '').trim();
    if (!key) return null;

    const cached = previewCache.get(key);
    if (cached !== undefined) return cached;

    const direct = directImageUrl(key);
    if (direct) {
        previewCache.set(key, direct);
        return direct;
    }

    try {
        const { data } = await axios.get(route('blockers.evidence-link-preview'), {
            params: { url: key },
        });
        const imageUrl = data?.image_url && typeof data.image_url === 'string' ? data.image_url : null;
        previewCache.set(key, imageUrl);
        return imageUrl;
    } catch {
        previewCache.set(key, null);
        return null;
    }
}

export function evidenceLinkHostname(url) {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}
