import { reactive } from 'vue';

/**
 * App-wide dialog service — replaces native window.alert / confirm / prompt
 * with a styled modal. A single <AppDialog /> (mounted in AppLayout) renders
 * this shared state; each call returns a Promise:
 *
 *   const dialog = useDialog();
 *   if (await dialog.confirm({ title, message })) { ... }   // → boolean
 *   const url = await dialog.prompt({ message });            // → string | null
 *   await dialog.alert({ message });                         // → true
 */
const state = reactive({
    open: false,
    type: 'confirm', // 'confirm' | 'prompt' | 'alert'
    title: '',
    message: '',
    confirmText: 'Đồng ý',
    cancelText: 'Huỷ',
    tone: 'default', // 'default' | 'danger'
    inputValue: '',
    placeholder: '',
    _resolve: null,
});

const settle = (result) => {
    const resolve = state._resolve;
    state.open = false;
    state._resolve = null;
    if (resolve) resolve(result);
};

const openDialog = (opts) =>
    new Promise((resolve) => {
        // Resolve any dialog still open (defensively) before showing the next.
        if (state._resolve) settle(state.type === 'prompt' ? null : false);

        state.type = opts.type ?? 'confirm';
        state.title = opts.title ?? '';
        state.message = opts.message ?? '';
        state.confirmText = opts.confirmText ?? (opts.type === 'alert' ? 'Đã hiểu' : 'Đồng ý');
        state.cancelText = opts.cancelText ?? 'Huỷ';
        state.tone = opts.tone ?? 'default';
        state.inputValue = opts.defaultValue ?? '';
        state.placeholder = opts.placeholder ?? '';
        state._resolve = resolve;
        state.open = true;
    });

// Used by AppDialog buttons / interactions.
export const dialogState = state;
export const onDialogConfirm = () => settle(state.type === 'prompt' ? state.inputValue : true);
export const onDialogCancel = () => settle(state.type === 'prompt' ? null : false);

export function useDialog() {
    return {
        confirm: (opts = {}) => openDialog({ ...opts, type: 'confirm' }),
        prompt: (opts = {}) => openDialog({ ...opts, type: 'prompt' }),
        alert: (opts = {}) => openDialog({ ...opts, type: 'alert' }),
    };
}
