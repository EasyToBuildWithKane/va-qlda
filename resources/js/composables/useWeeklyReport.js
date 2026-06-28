import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';

/**
 * Logic tab "Báo cáo tuần": chọn tuần, tạo/tổng hợp/regenerate, lưu chỉnh sửa
 * (3 thẻ chính + tóm tắt điều hành) với theo dõi dirty. Mọi thao tác server đi
 * qua Inertia router; backend redirect về Project/Show với ?wr=id.
 */
export function useWeeklyReport(projectId, { overview, detail }) {
    const toast = useToast();

    const processing = ref(false);
    const pendingWeek = ref(null); // tuần được chọn nhưng chưa có report

    // Buffer chỉnh sửa cục bộ cho 3 thẻ + executive summary.
    const draft = reactive({ executive_summary: '', sections: {} });
    const editing = ref(false);

    const report = computed(() => detail.value);
    const sprint = computed(() => overview.value?.sprint ?? null);
    const weeks = computed(() => overview.value?.weeks ?? []);
    const currentWeekNumber = computed(() => overview.value?.current_week ?? 1);

    const editableSections = computed(() =>
        (report.value?.sections ?? []).filter((s) => s.editable),
    );

    function resetDraft() {
        draft.executive_summary = report.value?.executive_summary ?? '';
        draft.sections = {};
        for (const s of editableSections.value) {
            draft.sections[s.section] = s.content ?? '';
        }
    }

    const dirty = computed(() => {
        if (!report.value) return false;
        if ((report.value.executive_summary ?? '') !== draft.executive_summary) return true;
        return editableSections.value.some(
            (s) => (s.content ?? '') !== (draft.sections[s.section] ?? ''),
        );
    });

    // Khi report thay đổi (đổi tuần / reload) → đồng bộ buffer, thoát chế độ sửa.
    watch(report, () => {
        editing.value = false;
        resetDraft();
    }, { immediate: true });

    function selectWeek(week) {
        if (week.report_id) {
            pendingWeek.value = null;
            router.get(
                route('projects.show', projectId),
                { tab: 'weekly', wr: week.report_id },
                { only: ['weeklyReport'], preserveScroll: true, preserveState: true },
            );
        } else {
            pendingWeek.value = week.week_number;
        }
    }

    function generateForWeek(weekNumber) {
        if (processing.value) return;
        processing.value = true;
        router.post(
            route('projects.weekly-reports.store', projectId),
            { week_number: weekNumber },
            {
                preserveScroll: true,
                onSuccess: () => { pendingWeek.value = null; },
                onError: () => toast.error('Không tạo được báo cáo tuần.'),
                onFinish: () => { processing.value = false; },
            },
        );
    }

    function regenerate({ preserve = true } = {}) {
        if (!report.value || processing.value) return;
        processing.value = true;
        const name = preserve ? 'projects.weekly-reports.regenerate' : 'projects.weekly-reports.generate';
        router.post(
            route(name, [projectId, report.value.id]),
            {},
            {
                preserveScroll: true,
                onError: () => toast.error('Không tạo lại được báo cáo.'),
                onFinish: () => { processing.value = false; },
            },
        );
    }

    function startEdit() {
        resetDraft();
        editing.value = true;
    }

    function cancelEdit() {
        resetDraft();
        editing.value = false;
    }

    function save() {
        if (!report.value || processing.value) return;
        processing.value = true;
        const payload = {
            executive_summary: draft.executive_summary,
            sections: Object.entries(draft.sections).map(([section, content]) => ({ section, content })),
        };
        router.put(
            route('projects.weekly-reports.update', [projectId, report.value.id]),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => { editing.value = false; },
                onError: () => toast.error('Không lưu được báo cáo.'),
                onFinish: () => { processing.value = false; },
            },
        );
    }

    return {
        processing,
        pendingWeek,
        report,
        sprint,
        weeks,
        currentWeekNumber,
        draft,
        editing,
        dirty,
        selectWeek,
        generateForWeek,
        regenerate,
        startEdit,
        cancelEdit,
        save,
    };
}
