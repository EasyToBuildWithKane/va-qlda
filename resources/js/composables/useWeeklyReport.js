import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';

/**
 * Logic tab "Báo cáo tuần": chọn tuần, tạo/tổng hợp/regenerate, lưu chỉnh sửa
 * (3 thẻ chính + tóm tắt điều hành) với theo dõi dirty. Mọi thao tác server đi
 * qua Inertia router; backend redirect về Project/Show với ?wr=id.
 *
 * @param {import('vue').Ref<string>|string} [options.tab] Tab hiện tại (`overview` | `weekly`)
 *   để giữ nguyên sau chọn tuần / tạo / lưu.
 */
export function useWeeklyReport(projectId, { overview, detail, tab } = {}) {
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

    function resolveTab() {
        const raw = tab && typeof tab === 'object' && 'value' in tab ? tab.value : tab;
        if (raw === 'overview' || raw === 'weekly') return raw;
        try {
            const fromUrl = new URLSearchParams(window.location.search).get('tab');
            if (fromUrl === 'overview' || fromUrl === 'weekly') return fromUrl;
        } catch {
            /* ignore */
        }
        return 'weekly';
    }

    const sectionList = computed(() => {
        const s = report.value?.sections;
        if (Array.isArray(s)) return s;
        if (Array.isArray(s?.data)) return s.data;
        return [];
    });

    const editableSections = computed(() => sectionList.value.filter((s) => s.editable));

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
                { tab: resolveTab(), wr: week.report_id },
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
            { week_number: weekNumber, tab: resolveTab() },
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
            { tab: resolveTab() },
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

    function transition(name, data = {}, okMsg) {
        if (!report.value || processing.value) return;
        processing.value = true;
        router.post(
            route(`projects.weekly-reports.${name}`, [projectId, report.value.id]),
            { ...data, tab: resolveTab() },
            {
                preserveScroll: true,
                onSuccess: () => { if (okMsg) toast.success(okMsg); },
                onError: (errors) => toast.error(errors?.reason || 'Thao tác không thành công.'),
                onFinish: () => { processing.value = false; },
            },
        );
    }

    const submit = () => transition('submit');
    const approve = () => transition('approve');
    const reject = (reason) => transition('reject', { reason });

    function save() {
        if (!report.value || processing.value) return;
        processing.value = true;
        const payload = {
            executive_summary: draft.executive_summary,
            sections: Object.entries(draft.sections).map(([section, content]) => ({ section, content })),
            tab: resolveTab(),
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
        sectionList,
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
        submit,
        approve,
        reject,
    };
}
