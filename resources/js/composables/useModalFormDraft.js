import { computed, unref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { draftHasMeaningfulContent } from '@/composables/useModalDraftHelpers';

export function formatModalDraftSavedAt(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function shallowPickDraft(obj, keys) {
    return keys.reduce((acc, key) => {
        const val = obj[key];
        if (Array.isArray(val)) {
            acc[key] = val.map((item) => (
                item && typeof item === 'object' ? { ...item } : item
            ));
        } else if (val && typeof val === 'object') {
            acc[key] = { ...val };
        } else {
            acc[key] = val;
        }
        return acc;
    }, {});
}

/** @deprecated Prefer draftHasMeaningfulContent */
export function draftHasAnyContent(data) {
    return draftHasMeaningfulContent(data);
}

export { draftHasMeaningfulContent };

/**
 * @param {string} modalKey
 * @param {object} [options]
 * @param {() => string|number|null|undefined} [options.getScope]
 * @param {string[]|null} [options.fields]
 * @param {(formData: object) => object} [options.pick]
 * @param {(data: object) => boolean} [options.hasContent]
 * @param {import('vue').Ref<number>|null} [options.openEpochRef] — tăng mỗi lần mở modal để huỷ restore cũ
 */
export function useModalFormDraft(modalKey, options = {}) {
    const {
        getScope = () => '',
        fields = null,
        pick = null,
        hasContent = draftHasMeaningfulContent,
        openEpochRef = null,
    } = options;

    const page = usePage();
    const dialog = useDialog();
    const toast = useToast();

    const storageKey = computed(() => {
        const uid = page.props.auth?.user?.id ?? 'guest';
        const scope = unref(getScope);
        const scopePart = scope != null && scope !== '' ? `.${scope}` : '';
        return `va-qlda.modal-draft.${modalKey}${scopePart}.${uid}`;
    });

    const read = () => {
        try {
            const raw = localStorage.getItem(storageKey.value);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    };

    const write = (payload) => {
        localStorage.setItem(storageKey.value, JSON.stringify({
            savedAt: new Date().toISOString(),
            ...payload,
        }));
    };

    const clear = () => {
        localStorage.removeItem(storageKey.value);
    };

    const pickData = (formData) => {
        if (pick) return pick(formData);
        if (fields) return shallowPickDraft(formData, fields);
        return JSON.parse(JSON.stringify(formData));
    };

    const saveFromForm = (formData, meta = {}) => {
        const data = pickData(formData);
        if (!hasContent(data)) return false;
        write({ data, meta });
        return true;
    };

    /** Gọi khi đóng modal còn dirty (sau khi user xác nhận thoát). */
    const saveOnClose = (formData, meta = {}) => {
        if (saveFromForm(formData, meta)) {
            toast.info('Đã lưu bản nháp trên trình duyệt.');
        }
    };

    /**
     * @param {(data: object, meta: object) => void} applyFn
     * @param {object} [opts]
     * @param {() => boolean} [opts.isActive] — modal vẫn mở sau await (tránh race)
     * @param {string|null} [opts.entityRevision] — so khớp meta.entityRevision khi restore
     * @param {number} [opts.openEpoch] — epoch lúc bắt đầu restore
     */
    const tryRestore = async (applyFn, opts = {}) => {
        const entry = read();
        if (!entry?.data || !hasContent(entry.data)) return false;

        const epochAtStart = opts.openEpoch ?? openEpochRef?.value ?? 0;
        const when = entry.savedAt ? formatModalDraftSavedAt(entry.savedAt) : '';
        const savedRevision = entry.meta?.entityRevision ?? null;
        const currentRevision = opts.entityRevision ?? null;
        const revisionMismatch = savedRevision != null
            && currentRevision != null
            && String(savedRevision) !== String(currentRevision);

        let message = when
            ? `Có bản nháp đã lưu lúc ${when}. Bạn muốn tiếp tục nhập?`
            : 'Có bản nháp chưa gửi. Bạn muốn tiếp tục nhập?';
        if (revisionMismatch) {
            message += ' Dữ liệu trên hệ thống có thể đã thay đổi kể từ lúc lưu nháp.';
        }

        const ok = await dialog.confirm({
            title: 'Tiếp tục bản nháp?',
            message,
            confirmText: 'Tiếp tục',
            cancelText: 'Bắt đầu mới',
        });

        if (typeof opts.isActive === 'function' && !opts.isActive()) return false;
        if (openEpochRef != null && openEpochRef.value !== epochAtStart) return false;
        if (opts.openEpoch != null && opts.openEpoch !== epochAtStart) return false;

        if (ok) {
            applyFn(entry.data, entry.meta ?? {});
            return true;
        }
        clear();
        return false;
    };

    const bumpOpenEpoch = () => {
        if (openEpochRef) openEpochRef.value += 1;
        return openEpochRef?.value ?? 0;
    };

    return {
        read,
        clear,
        saveOnClose,
        tryRestore,
        pickData,
        bumpOpenEpoch,
    };
}
