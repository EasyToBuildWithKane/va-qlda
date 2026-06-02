import { useDialog } from '@/composables/useDialog';

/**
 * Confirm before closing a modal or discarding edits.
 * @param {() => void} onClose
 * @returns {(dirty?: boolean, opts?: object) => Promise<void>}
 */
export function useConfirmClose(onClose) {
    const dialog = useDialog();

    return async (dirty = false, opts = {}) => {
        if (dirty) {
            const ok = await dialog.confirm({
                title: opts.title ?? 'Huỷ thao tác?',
                message: opts.message ?? 'Thay đổi chưa được lưu sẽ bị mất. Bạn có chắc muốn thoát?',
                confirmText: opts.confirmText ?? 'Thoát',
                cancelText: opts.cancelText ?? 'Tiếp tục nhập',
                tone: opts.tone ?? 'default',
            });
            if (!ok) return;
        }
        onClose();
    };
}

/**
 * Confirm before destructive delete actions.
 * @returns {(message: string, onConfirm: () => void, opts?: object) => Promise<void>}
 */
export function useConfirmDelete() {
    const dialog = useDialog();

    return async (message, onConfirm, opts = {}) => {
        const ok = await dialog.confirm({
            title: opts.title ?? 'Xác nhận xoá',
            message,
            tone: 'danger',
            confirmText: opts.confirmText ?? 'Xoá',
            cancelText: opts.cancelText ?? 'Huỷ',
        });
        if (ok) onConfirm();
    };
}
