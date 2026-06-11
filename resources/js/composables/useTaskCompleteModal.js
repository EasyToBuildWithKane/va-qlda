import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';
import { resolveHoursTiming, hoursTimingMeta, slaResultMeta } from '@/composables/useTaskCompletion';
import { getTaskEstimateDeadline } from '@/composables/useTaskTimeliness';

const open = ref(false);
const targetTask = ref(null);
const pendingHooks = ref(null);
const actualHours = ref('');
const completionNote = ref('');
const submitting = ref(false);

/**
 * Modal hoàn thành task dùng chung (singleton state).
 */
export function useTaskCompleteModal() {
    const toast = useToast();

    const estimateHours = computed(() => {
        const t = targetTask.value;
        const v = Number(t?.estimate_hours);
        return Number.isFinite(v) && v > 0 ? v : null;
    });

    const previewTiming = computed(() => {
        const act = Number(actualHours.value);
        if (!Number.isFinite(act) || act <= 0) return null;
        const key = resolveHoursTiming(estimateHours.value, act);
        return key ? hoursTimingMeta(key) : null;
    });

    const previewSla = computed(() => {
        const act = Number(actualHours.value);
        if (!Number.isFinite(act) || act <= 0) return null;
        const est = estimateHours.value;
        if (est && act > est + 0.05) {
            return slaResultMeta('exceeded');
        }
        const t = targetTask.value;
        const deadline = getTaskEstimateDeadline(t);
        if (deadline && new Date() > deadline) {
            return slaResultMeta('exceeded');
        }
        if (est || deadline) {
            return slaResultMeta('met');
        }
        return null;
    });

    const dirty = computed(() => actualHours.value !== '' || completionNote.value.trim() !== '');

    const requestComplete = (task, hooks = {}) => {
        if (!task?.id) return;
        targetTask.value = task;
        pendingHooks.value = hooks;
        actualHours.value = task.actual_hours != null ? String(task.actual_hours) : '';
        completionNote.value = task.completion_note || '';
        open.value = true;
    };

    const close = () => {
        pendingHooks.value?.onCancel?.();
        open.value = false;
        targetTask.value = null;
        pendingHooks.value = null;
        actualHours.value = '';
        completionNote.value = '';
    };

    const submit = (projectId) => {
        const task = targetTask.value;
        const act = Number(actualHours.value);
        if (!task?.id || !Number.isFinite(act) || act <= 0) {
            toast.error('Vui lòng nhập số giờ thực tế (lớn hơn 0).');
            return;
        }

        submitting.value = true;
        router.patch(`/projects/${projectId}/tasks/${task.id}`, {
            status: 'done',
            actual_hours: act,
            completion_note: completionNote.value.trim() || null,
        }, {
            preserveScroll: true,
            only: ['tasks'],
            onSuccess: () => {
                toast.success(`#${task.id} đã hoàn thành · ${act}h thực tế`);
                const hooks = pendingHooks.value;
                open.value = false;
                targetTask.value = null;
                pendingHooks.value = null;
                actualHours.value = '';
                completionNote.value = '';
                hooks?.onSuccess?.();
            },
            onError: (errors) => {
                const msg = errors?.actual_hours || errors?.status || 'Không hoàn thành được công việc.';
                toast.error(Array.isArray(msg) ? msg[0] : msg);
                pendingHooks.value?.onError?.();
            },
            onFinish: () => { submitting.value = false; },
        });
    };

    return {
        open,
        targetTask,
        actualHours,
        completionNote,
        submitting,
        estimateHours,
        previewTiming,
        previewSla,
        dirty,
        requestComplete,
        close,
        submit,
    };
}
