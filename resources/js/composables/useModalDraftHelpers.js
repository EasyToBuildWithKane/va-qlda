/**
 * Helpers for modal localStorage drafts (useModalFormDraft).
 */

/** Revision token saved with draft — compared on restore (e.g. entity updated_at). */
export function entityRevisionFrom(source) {
    if (source == null) return null;
    if (typeof source === 'string' || typeof source === 'number') return String(source);
    if (typeof source === 'object') {
        const v = source.updated_at ?? source.updatedAt ?? null;
        return v != null ? String(v) : null;
    }
    return null;
}

export function buildDraftSaveMeta(entity, extra = {}) {
    return {
        entityRevision: entityRevisionFrom(entity),
        ...extra,
    };
}

/** After programmatic fill, align Inertia useForm dirty baseline (v2+). */
export function syncInertiaFormBaseline(form) {
    if (form && typeof form.defaults === 'function') {
        form.defaults();
    }
}

const DEFAULT_IGNORE_KEYS = new Set([
    'is_active',
    'is_milestone',
    'commentable_type',
    'commentable_id',
]);

function stringHasText(v) {
    return typeof v === 'string' && v.trim().length > 0;
}

function valueIsMeaningful(v) {
    if (typeof v === 'string') return v.trim().length > 0;
    if (typeof v === 'number') return Number.isFinite(v);
    if (typeof v === 'boolean') return false;
    if (Array.isArray(v)) {
        return v.some((item) => {
            if (typeof item === 'string') return item.trim().length > 0;
            if (item && typeof item === 'object') {
                return Object.values(item).some((x) => stringHasText(x) || (typeof x === 'number' && Number.isFinite(x)));
            }
            return item != null && item !== '';
        });
    }
    if (v && typeof v === 'object') {
        return Object.values(v).some((x) => valueIsMeaningful(x));
    }
    return v != null && v !== '';
}

/**
 * Stricter than draftHasAnyContent — skips lone booleans / empty shells.
 * @param {object} [options.ignoreKeys]
 * @param {string[]} [options.signalKeys] — any non-empty string here counts as content
 */
export function draftHasMeaningfulContent(data, options = {}) {
    if (!data || typeof data !== 'object') return false;

    const ignore = new Set([...DEFAULT_IGNORE_KEYS, ...(options.ignoreKeys ?? [])]);
    const signals = options.signalKeys ?? [];

    if (signals.some((k) => stringHasText(data[k]))) return true;

    if (data.form && typeof data.form === 'object') {
        return draftHasMeaningfulContent(data.form, options);
    }

    return Object.entries(data).some(([key, v]) => {
        if (ignore.has(key)) return false;
        return valueIsMeaningful(v);
    });
}

/** Sau hydrate form + optional restore nháp. */
export async function restoreModalDraft(formDraft, {
    isActive,
    openEpoch,
    entity,
    applyDraft,
    form = null,
}) {
    if (typeof isActive === 'function' && !isActive()) return false;

    const restored = await formDraft.tryRestore(applyDraft, {
        isActive,
        openEpoch,
        entityRevision: entityRevisionFrom(entity),
    });

    if (form) syncInertiaFormBaseline(form);
    return restored;
}
