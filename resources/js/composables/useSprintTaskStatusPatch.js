import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';
import { useTaskCompleteModal } from '@/composables/useTaskCompleteModal';
import { isTaskStatusLocked } from '@/composables/useTaskCompletion';

/**
 * PATCH trạng thái task từ bảng sprint: reload tasks, toast.
 */
export function useSprintTaskStatusPatch(projectId, statusOptions = []) {
    const toast = useToast();
    const { requestComplete } = useTaskCompleteModal();

    const labelFor = (value) =>
        statusOptions.find((o) => o.value === value)?.label ?? value;

    const patchTaskStatus = (row, status, hooks = {}) => {
        const prev = row?.status?.value;
        if (!row?.id || prev === status) return;

        if (isTaskStatusLocked(row) && status !== 'done') {
            toast.error('Công việc đã hoàn thành — không thể đổi trạng thái.');
            hooks.onError?.();
            return;
        }

        if (status === 'done' && prev !== 'done') {
            requestComplete(row, hooks);
            return;
        }

        router.patch(`/projects/${projectId}/tasks/${row.id}`, { status }, {
            preserveScroll: true,
            only: ['tasks'],
            onSuccess: () => {
                toast.success(`#${row.id} → ${labelFor(status)}`);
                hooks.onSuccess?.();
            },
            onError: () => {
                toast.error('Không đổi được trạng thái. Cần quyền thành viên dự án.');
                hooks.onError?.();
            },
        });
    };

    return { patchTaskStatus };
}
