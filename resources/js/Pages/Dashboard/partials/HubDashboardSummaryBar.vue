<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const cards = computed(() => {
    const s = props.summary;
    const totalProjects = s.total_projects ?? 0;
    const active = s.active_projects ?? 0;
    const pctActive = totalProjects > 0 ? Math.round((active / totalProjects) * 100) : 0;
    const openBlockers = s.open_blockers ?? 0;
    const overdue = s.overdue_tasks ?? 0;
    const openFeedback = s.open_feedback ?? 0;
    const isLead = Boolean(s.is_lead_tier);

    const base = [
        {
            key: 'active_projects',
            label: 'Dự án đang chạy',
            value: active,
            tone: 'brand',
            icon: 'all-projects',
            sub: totalProjects
                ? `${pctActive}% trên ${totalProjects} dự án`
                : 'Chưa có dự án trong hệ thống',
            progress: pctActive,
            interactive: true,
            href: '/projects',
        },
        {
            key: 'open_blockers',
            label: 'vướng mắc đang mở',
            value: openBlockers,
            tone: openBlockers > 0 ? 'amber' : 'emerald',
            icon: 'blockers',
            sub: openBlockers > 0 ? 'Cần theo dõi xử lý' : 'Không có vướng mắc mở',
            interactive: true,
            href: '/blockers',
        },
        {
            key: 'overdue_tasks',
            label: 'Công việc quá hạn',
            value: overdue,
            tone: overdue > 0 ? 'rose' : 'emerald',
            icon: 'clock',
            sub: overdue > 0 ? 'Ưu tiên trên dashboard công việc' : 'Không có hạn trễ',
            interactive: true,
            href: route('work-dashboard'),
        },
        {
            key: 'open_feedback',
            label: 'Phản hồi đang xử lý',
            value: openFeedback,
            tone: 'violet',
            icon: 'feedback',
            sub: openFeedback > 0 ? 'Theo dõi phản hồi người dùng' : 'Hàng đợi phản hồi trống',
            interactive: true,
            href: '/feedback',
        },
    ];

    if (isLead) {
        const pending = s.pending_reports ?? 0;
        base.push({
            key: 'pending_reports',
            label: 'Báo cáo chờ duyệt',
            value: pending,
            tone: pending > 0 ? 'amber' : 'sky',
            icon: 'daily',
            sub: pending > 0 ? 'Cần duyệt trên màn hình review' : 'Không có báo cáo chờ',
            interactive: true,
            href: '/daily-reports/review',
        });
    } else {
        const openTasks = s.open_tasks ?? 0;
        base.push({
            key: 'open_tasks',
            label: 'Công việc chưa xong',
            value: openTasks,
            tone: 'sky',
            icon: 'task',
            sub: openTasks > 0 ? 'Trên toàn hệ thống' : 'Không còn việc mở',
            interactive: true,
            href: route('work-dashboard'),
        });
    }

    return base;
});

function onSelect(card) {
    if (card.href) {
        router.visit(card.href);
    }
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê tổng quan hệ thống"
    heading="Chỉ số vận hành liên module"
    hint="Thẻ có viền nét đứt — bấm để mở module liên quan"
    :cards="cards"
    :progress-denominator="summary.total_projects ?? 0"
    @select="onSelect"
  />
</template>
