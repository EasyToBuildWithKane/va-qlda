import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const PROJECT_SHOW_PATH = /^\/projects\/(\d+)$/;

/** Nhãn tab trang chi tiết dự án — khớp `Show.vue` tabs */
export const PROJECT_SHOW_TAB_LABELS = {
    overview: 'Tổng quan',
    documents: 'Tài liệu',
    timeline: 'Tiến độ / Gantt',
    board: 'Kanban',
    sprints: 'Sprint',
    blockers: 'Vướng mắc',
    feedback: 'Phản hồi',
};

/**
 * @param {import('vue').Ref<boolean>|boolean} [pulseRef]
 */
export function useQuickBlockerReport(pulseRef = null) {
    const page = usePage();

    const showModal = ref(false);
    const initialDescription = ref('');
    const localPulse = ref(false);
    const pulse = pulseRef ?? localPulse;

    const quickBlocker = computed(() => page.props.quickBlocker ?? null);
    const canReport = computed(() => Boolean(quickBlocker.value?.canReport));

    const projects = computed(() => quickBlocker.value?.projects ?? []);
    const employees = computed(() => quickBlocker.value?.employees ?? []);
    const severityOptions = computed(() => quickBlocker.value?.enums?.blockerSeverity ?? []);
    const statusOptions = computed(() => quickBlocker.value?.enums?.blockerStatus ?? []);

    const path = computed(() => page.url.split('?')[0]);

    const projectFromProps = computed(() => page.props.project ?? null);

    const defaultProjectId = computed(() => {
        const fromProps = projectFromProps.value?.id;
        if (fromProps != null) return fromProps;
        const m = path.value.match(PROJECT_SHOW_PATH);
        return m ? Number(m[1]) : null;
    });

    const lockProject = computed(() => {
        if (projectFromProps.value?.id != null) return true;
        return PROJECT_SHOW_PATH.test(path.value);
    });

    const activeProject = computed(() => {
        const id = defaultProjectId.value;
        if (id == null) return null;
        return projects.value.find((p) => p.id === id)
            ?? (projectFromProps.value?.id === id ? projectFromProps.value : null);
    });

    const projectName = computed(() => activeProject.value?.name ?? '');
    const projectCode = computed(() => activeProject.value?.code ?? '');

    /** Tạo mới: sau khi ghi nhận, người báo được quyền `update` blocker → upload minh chứng hợp lệ. */
    const canUploadAttachments = computed(() => canReport.value);

    const buildInitialDescription = () => {
        const url = new URL(window.location.href);
        let locationLine = '';
        if (PROJECT_SHOW_PATH.test(path.value)) {
            const tabKey = url.searchParams.get('tab');
            const tabLabel = tabKey ? PROJECT_SHOW_TAB_LABELS[tabKey] : null;
            if (tabLabel) {
                locationLine = `\nVị trí: tab «${tabLabel}»\n\n`;
            }
        }
        return `Liên kết trang đang xem:\n${url.toString()}${locationLine}`;
    };

    const open = () => {
        if (!canReport.value) return;
        initialDescription.value = buildInitialDescription();
        pulse.value = true;
        showModal.value = true;
        window.setTimeout(() => {
            pulse.value = false;
        }, 520);
    };

    const close = () => {
        showModal.value = false;
        initialDescription.value = '';
    };

    return {
        showModal,
        initialDescription,
        pulse,
        canReport,
        projects,
        employees,
        severityOptions,
        statusOptions,
        defaultProjectId,
        lockProject,
        projectName,
        projectCode,
        canUploadAttachments,
        open,
        close,
    };
}
