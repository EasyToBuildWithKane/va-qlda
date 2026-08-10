<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    kpi: { type: Object, default: () => ({}) },
});

const cards = computed(() => {
    const k = props.kpi || {};
    const n = (v) => Number(v ?? 0);
    const total = n(k.total_tasks);
    const completed = n(k.completed_tasks);
    const pct = (v) => (total > 0 ? Math.round((v / total) * 100) : 0);

    return [
        {
            key: 'progress',
            label: 'Tiến độ Sprint',
            value: `${n(k.sprint_progress)}%`,
            tone: 'brand',
            icon: 'overview',
            sub: total ? `${completed}/${total} hạng mục` : 'Chưa có công việc',
            progress: n(k.sprint_progress),
            interactive: false,
        },
        {
            key: 'completed',
            label: 'Hoàn thành',
            value: `${completed} / ${total}`,
            tone: 'emerald',
            icon: 'check-circle',
            sub: total ? `${pct(completed)}% sprint` : 'Chưa có công việc',
            progress: pct(completed),
            interactive: false,
        },
        {
            key: 'remaining',
            label: 'Còn lại',
            value: n(k.remaining_tasks),
            tone: 'sky',
            icon: 'task',
            sub: total ? `${pct(n(k.remaining_tasks))}% chưa xong` : 'Chưa có công việc',
            progress: pct(n(k.remaining_tasks)),
            interactive: false,
        },
        {
            key: 'overdue',
            label: 'Quá hạn',
            value: n(k.overdue),
            tone: n(k.overdue) ? 'rose' : 'slate',
            icon: 'clock',
            sub: n(k.overdue) ? 'Cần xử lý ưu tiên' : 'Không quá hạn',
            interactive: false,
        },
        {
            key: 'issues',
            label: 'Test case mở',
            value: n(k.open_issues),
            tone: n(k.open_issues) ? 'amber' : 'slate',
            icon: 'alert',
            sub: n(k.open_issues) ? 'Đang theo dõi' : 'Không test case mở',
            interactive: false,
        },
        {
            key: 'feedback',
            label: 'Phản hồi',
            value: n(k.feedback),
            tone: 'violet',
            icon: 'feedback',
            sub: n(k.feedback) ? 'Trong phạm vi tuần' : 'Chưa có phản hồi',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    variant="embedded"
    aria-label="Thống kê báo cáo tuần"
    heading="Chỉ số tuần"
    hint="Tổng hợp từ Sprint và dữ liệu dự án"
    :cards="cards"
    :progress-denominator="Number(kpi?.total_tasks ?? 0)"
    grid-class="grid-cols-2 sm:grid-cols-3 lg:grid-cols-6"
    shell-class="kpi-strip relative overflow-x-hidden rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm dark:border-slate-700 dark:from-slate-900/80 dark:to-slate-900 sm:px-5 sm:py-5"
  />
</template>
