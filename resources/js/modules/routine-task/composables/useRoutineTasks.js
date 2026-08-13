import { router } from '@inertiajs/vue3';

/**
 * Inertia mutations for /routine-tasks.
 */
export function useRoutineTasks() {
    const createTask = (payload, options = {}) => {
        router.post('/routine-tasks', payload, {
            preserveScroll: true,
            ...options,
        });
    };

    const updateTask = (id, payload, options = {}) => {
        router.put(`/routine-tasks/${id}`, payload, {
            preserveScroll: true,
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

    return {
        createTask,
        updateTask,
        toggleStatus,
        deleteTask,
        reorder,
    };
}
