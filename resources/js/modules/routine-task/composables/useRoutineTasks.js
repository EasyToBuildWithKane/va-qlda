import { router } from '@inertiajs/vue3';

/**
 * Inertia mutations for /routine-tasks.
 */
export function useRoutineTasks() {
    const createTask = (payload, options = {}) => {
        const hasFiles = Array.isArray(payload.files) && payload.files.length > 0;
        router.post('/routine-tasks', payload, {
            preserveScroll: true,
            forceFormData: hasFiles,
            ...options,
        });
    };

    const updateTask = (id, payload, options = {}) => {
        const hasFiles = Array.isArray(payload.files) && payload.files.length > 0;
        router.put(`/routine-tasks/${id}`, payload, {
            preserveScroll: true,
            forceFormData: hasFiles,
            ...options,
        });
    };

    const toggleStatus = (id, status = null, options = {}) => {
        const body = status ? { status } : {};
        router.post(`/routine-tasks/${id}/toggle-status`, body, {
            preserveScroll: true,
            ...options,
        });
    };

    const deleteTask = (id, options = {}) => {
        router.delete(`/routine-tasks/${id}`, {
            preserveScroll: true,
            ...options,
        });
    };

    const reorder = (ids, options = {}) => {
        router.post('/routine-tasks/reorder', { ids }, {
            preserveScroll: true,
            ...options,
        });
    };

    const deleteAttachment = (taskId, attachmentId, options = {}) => {
        router.delete(`/routine-tasks/${taskId}/attachments/${attachmentId}`, {
            preserveScroll: true,
            ...options,
        });
    };

    return {
        createTask,
        updateTask,
        toggleStatus,
        deleteTask,
        reorder,
        deleteAttachment,
    };
}

export function todayIso() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
}

export function hoursBetween(start, end) {
    if (!start || !end) return null;
    const [sh, sm] = String(start).split(':').map(Number);
    const [eh, em] = String(end).split(':').map(Number);
    if (![sh, sm, eh, em].every(Number.isFinite)) return null;
    let mins = (eh * 60 + em) - (sh * 60 + sm);
    if (mins <= 0) mins += 24 * 60;
    return Math.round((mins / 60) * 100) / 100;
}

export function hoursLabel(value) {
    if (value == null || value === '') return null;
    const n = Number(value);
    if (!Number.isFinite(n)) return null;
    const rounded = Math.round(n * 10) / 10;
    return `${Number.isInteger(rounded) ? rounded : rounded.toFixed(1)}h`;
}

export function formatClock(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

export function formatViDate(isoDate) {
    if (!isoDate) return null;
    const d = new Date(`${isoDate}T00:00:00`);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('vi-VN', { weekday: 'short', day: '2-digit', month: '2-digit' });
}

export function addMinutesToTime(time, minutes) {
    if (!time) return '';
    const [h, m] = String(time).split(':').map(Number);
    if (![h, m].every(Number.isFinite)) return '';
    const total = (h * 60 + m + minutes + 24 * 60) % (24 * 60);
    const hh = String(Math.floor(total / 60)).padStart(2, '0');
    const mm = String(total % 60).padStart(2, '0');
    return `${hh}:${mm}`;
}
