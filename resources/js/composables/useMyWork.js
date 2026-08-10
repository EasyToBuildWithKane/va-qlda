import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';
import { useTaskCompleteModal } from '@/composables/useTaskCompleteModal';
import { isTaskStatusLocked } from '@/composables/useTaskCompletion';

/**
 * Thao tác nhanh cho màn hình "Việc của tôi" — tái dùng nguyên các endpoint
 * project-scoped đã có (không tạo API ghi mới):
 *   - Đổi trạng thái: PATCH /projects/{project}/tasks/{task}   (projects.tasks.status)
 *   - Log giờ      : POST  /projects/{project}/tasks/{task}/worklogs (projects.worklogs.store)
 *
 * Chi tiết task mặc định mở modal trên /my-work (`MyWorkTaskDetailModal`);
 * `openTaskInProject` chỉ khi user chọn «Mở trong dự án».
 *
 * Mọi mutation dùng back()-redirect của server → Inertia reload props /my-work,
 * giữ nguyên ngữ cảnh (?member=) + vị trí cuộn (preserveScroll).
 */
export function useMyWork() {
    const toast = useToast();
    const { requestComplete } = useTaskCompleteModal();

    const changeStatus = (task, status, opts = {}) => {
        if (!task?.project_id || !task?.id) return;

        const prev = task.status?.value;
        if (prev === status) return;

        if (isTaskStatusLocked(task) && status !== 'done') {
            toast.error('Công việc đã hoàn thành — không thể đổi trạng thái.');
            return;
        }

        if (status === 'done' && prev !== 'done') {
            requestComplete(task, opts, { reloadAll: true });
            return;
        }

        router.patch(
            `/projects/${task.project_id}/tasks/${task.id}`,
            { status },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Đã cập nhật trạng thái.'),
                onError: (errors) => {
                    const msg = errors?.status || errors?.actual_hours;
                    toast.error(
                        msg
                            ? (Array.isArray(msg) ? msg[0] : msg)
                            : 'Không thể cập nhật trạng thái.',
                    );
                },
                ...opts,
            },
        );
    };

    const logWork = (task, payload, opts = {}) => {
        if (!task?.project_id || !task?.id) return;

        router.post(
            `/projects/${task.project_id}/tasks/${task.id}/worklogs`,
            { hours: payload.hours, note: payload.note ?? null },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Đã ghi nhận giờ làm.'),
                onError: () => toast.error('Không ghi được giờ. Kiểm tra lại số giờ (0.25–24).'),
                ...opts,
            },
        );
    };

    /** Mở task trong trang dự án (liên kết phụ — chi tiết mặc định dùng modal trên /my-work). */
    const openTaskInProject = (task) => {
        if (!task?.project_id || !task?.id) return;
        router.visit(`/projects/${task.project_id}?task=${task.id}`);
    };

    /** @deprecated Dùng modal trên Index; giữ alias để không gãy import cũ. */
    const openTask = openTaskInProject;

    /** Chuyển chế độ self ↔ team, hoặc xem 1 thành viên. */
    const goTo = ({ scope = 'self', member = null } = {}) => {
        const params = {};
        if (member) params.member = member;
        else if (scope === 'team') params.scope = 'team';

        router.get('/my-work', params, { preserveScroll: true });
    };

    return { changeStatus, logWork, openTask, openTaskInProject, goTo };
}
