import { reactive } from 'vue';

const toasts = reactive([]);
let _id = 1;

const remove = (id) => {
    const idx = toasts.findIndex((t) => t.id === id);
    if (idx !== -1) toasts.splice(idx, 1);
};

const add = (type, message, duration = 3500) => {
    const id = _id++;
    toasts.push({ id, type, message });
    setTimeout(() => remove(id), duration);
};

export const toastList = toasts;
export const dismissToast = remove;

export function useToast() {
    return {
        success: (msg, dur) => add('success', msg, dur),
        error: (msg, dur) => add('error', msg, dur),
        info: (msg, dur) => add('info', msg, dur),
        warning: (msg, dur) => add('warning', msg, dur),
        dismiss: remove,
    };
}
