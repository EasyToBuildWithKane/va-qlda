import { defineStore } from 'pinia';
import { reactive, ref } from 'vue';

const toasts = reactive([]);
let _toastId = 1;

export const useUiStore = defineStore('ui', () => {
    const sidebarOpen = ref(true);
    const loading = ref(false);

    // ── Toast ──────────────────────────────────────────────────────────────

    const addToast = (type, message, duration = 3500) => {
        const id = _toastId++;
        toasts.push({ id, type, message });
        setTimeout(() => removeToast(id), duration);
        return id;
    };

    const removeToast = (id) => {
        const idx = toasts.findIndex((t) => t.id === id);
        if (idx !== -1) toasts.splice(idx, 1);
    };

    const toast = {
        list: toasts,
        success: (msg, dur) => addToast('success', msg, dur),
        error: (msg, dur) => addToast('error', msg, dur),
        info: (msg, dur) => addToast('info', msg, dur),
        warning: (msg, dur) => addToast('warning', msg, dur),
        dismiss: removeToast,
    };

    // ── Dialog ────────────────────────────────────────────────────────────

    const dialog = reactive({ open: false, options: {} });

    const confirm = (options) =>
        new Promise((resolve) => {
            dialog.open = true;
            dialog.options = { ...options, resolve };
        });

    const resolveDialog = (result) => {
        dialog.open = false;
        dialog.options.resolve?.(result);
        dialog.options = {};
    };

    return {
        sidebarOpen,
        loading,
        toast,
        dialog,
        confirm,
        resolveDialog,
    };
});
