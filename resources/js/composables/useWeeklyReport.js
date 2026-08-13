import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';

const MAX_PERIOD_DAYS = 31;

/**
 * Logic tab "Báo cáo tuần": chọn khoảng ngày, tạo/tổng hợp/regenerate, lưu chỉnh sửa
 * (3 thẻ chính + tóm tắt điều hành) với theo dõi dirty. Mọi thao tác server đi
 * qua Inertia router; backend redirect về Project/Show với ?wr=id.
 *
 * @param {import('vue').Ref<string>|string} [options.tab] Tab hiện tại (`overview` | `weekly`)
 *   để giữ nguyên sau chọn kỳ / tạo / lưu.
 */
export function useWeeklyReport(projectId, { overview, detail, tab } = {}) {
    const toast = useToast();

    const processing = ref(false);
    const pendingPeriod = ref(null);

    // Buffer chỉnh sửa cục bộ cho 3 thẻ + executive summary.
    const draft = reactive({ executive_summary: '', sections: {} });
    const editing = ref(false);

    const report = computed(() => detail.value);
    const sprint = computed(() => overview.value?.sprint ?? null);
    const reports = computed(() => overview.value?.reports ?? []);
    const defaultStart = computed(() => overview.value?.default_start ?? '');
    const defaultEnd = computed(() => overview.value?.default_end ?? '');

    const periodStart = computed(() => (
        pendingPeriod.value?.start
        ?? report.value?.week_start
        ?? defaultStart.value
    ));
    const periodEnd = computed(() => (
        pendingPeriod.value?.end
        ?? report.value?.week_end
        ?? defaultEnd.value
    ));

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

    // Khi report thay đổi (đổi kỳ / reload) → đồng bộ buffer, thoát chế độ sửa.
    watch(report, () => {
        editing.value = false;
        resetDraft();
    }, { immediate: true });

    function findReport(start, end) {
        return reports.value.find((r) => r.week_start === start && r.week_end === end) ?? null;
    }

    function selectReport(id) {
        if (!id) {
            pendingPeriod.value = {
                start: defaultStart.value,
                end: defaultEnd.value,
            };
            if (report.value?.id) {
                router.get(
                    route('projects.show', projectId),
                    { tab: resolveTab() },
                    { only: ['weeklyReport'], preserveScroll: true, preserveState: true },
                );
            }
            return;
        }
        pendingPeriod.value = null;
        router.get(
            route('projects.show', projectId),
            { tab: resolveTab(), wr: id },
            { only: ['weeklyReport'], preserveScroll: true, preserveState: true },
        );
    }

    function selectPeriod({ start, end }) {
        const nextStart = start || periodStart.value;
        const nextEnd = end || periodEnd.value;
        if (!nextStart || !nextEnd) return;

        const match = findReport(nextStart, nextEnd);
        if (match) {
            pendingPeriod.value = null;
            if (report.value?.id !== match.id) {
                router.get(
                    route('projects.show', projectId),
                    { tab: resolveTab(), wr: match.id },
                    { only: ['weeklyReport'], preserveScroll: true, preserveState: true },
                );
            }
            return;
        }

        pendingPeriod.value = { start: nextStart, end: nextEnd };
        if (report.value?.id) {
            router.get(
                route('projects.show', projectId),
                { tab: resolveTab() },
                { only: ['weeklyReport'], preserveScroll: true, preserveState: true },
            );
        }
    }

    function generateForPeriod(start, end) {
        if (processing.value) return;
        const from = start || periodStart.value;
        const to = end || periodEnd.value;
        if (!from || !to) {
            toast.error('Vui lòng chọn ngày bắt đầu và ngày kết thúc.');
            return;
        }
        if (from > to) {
            toast.error('Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.');
            return;
        }
        const span = Math.round((new Date(`${to}T00:00:00`) - new Date(`${from}T00:00:00`)) / 86400000) + 1;
        if (span > MAX_PERIOD_DAYS) {
            toast.error(`Kỳ báo cáo tối đa ${MAX_PERIOD_DAYS} ngày.`);
            return;
        }
        processing.value = true;
        router.post(
            route('projects.weekly-reports.store', projectId),
            { week_start: from, week_end: to, tab: resolveTab() },
            {
                preserveScroll: true,
                onSuccess: () => { pendingPeriod.value = null; },
                onError: (errors) => toast.error(errors?.week_end || errors?.week_start || 'Không tạo được báo cáo tuần.'),
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
        pendingPeriod,
        report,
        sectionList,
        sprint,
        reports,
        periodStart,
        periodEnd,
        draft,
        editing,
        dirty,
        selectReport,
        selectPeriod,
        generateForPeriod,
        regenerate,
        startEdit,
        cancelEdit,
        save,
        submit,
        approve,
        reject,
    };
}
