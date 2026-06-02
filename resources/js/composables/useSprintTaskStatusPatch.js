import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';

const STATUS_EFFECT_HINT = {
    todo: 'Task về backlog — SLA giờ tạm dừng.',
    in_progress: 'Bắt đầu làm — SLA giờ ước tính chạy ngay.',
    in_review: 'Chờ duyệt — SLA vẫn tính cho đến khi hoàn thành.',
    done: 'Hoàn thành — tiến độ 100%, đóng SLA.',
    blocked: 'Bị chặn — SLA vẫn đang chạy (cần xử lý sớm).',
};

/**
 * PATCH trạng thái task từ bảng sprint: reload tasks, toast, gắn SLA/tiến độ.
 */
export function useSprintTaskStatusPatch(projectId, statusOptions = []) {
    const toast = useToast();

    const labelFor = (value) =>
        statusOptions.find((o) => o.value === value)?.label ?? value;

    const patchTaskStatus = (row, status, hooks = {}) => {
        const prev = row?.status?.value;
        if (!row?.id || prev === status) return;

        router.patch(`/projects/${projectId}/tasks/${row.id}`, { status }, {
            preserveScroll: true,
            only: ['tasks'],
            onSuccess: () => {
                const label = labelFor(status);
                let msg = `#${row.id} → ${label}`;
                const hint = STATUS_EFFECT_HINT[status];
                if (status === 'in_progress' && row.estimate_hours) {
                    msg += ` · SLA ${row.estimate_hours}h bắt đầu tính`;
                } else if (status === 'done') {
                    msg += ' · Tiến độ 100%';
                } else if (hint) {
                    msg += ` · ${hint}`;
                }
                toast.success(msg);
                hooks.onSuccess?.();
            },
            onError: () => {
                toast.error('Không đổi được trạng thái. Cần quyền thành viên dự án.');
                hooks.onError?.();
            },
        });
    };

    return { patchTaskStatus, statusEffectHint: STATUS_EFFECT_HINT };
}
