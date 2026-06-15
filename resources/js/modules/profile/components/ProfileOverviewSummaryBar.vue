<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { profileDisplayValue } from '../utils/profileDisplay';

const props = defineProps({
    stats: { type: Object, required: true },
    activeTab: { type: String, default: 'overview' },
});

const emit = defineEmits(['go-tab']);

const cards = computed(() => {
    const s = props.stats;
    const completion = s.profile_completion ?? 0;
    const skillScore = s.skill_score;
    const projects = s.projects_total ?? 0;
    const tasksDone = s.tasks_done ?? 0;
    const tasksTotal = s.tasks_total ?? 0;
    const hours = Math.round(s.worklog_hours ?? 0);

    return [
        {
            key: 'completion',
            label: 'Hoàn thiện hồ sơ',
            value: `${completion}%`,
            tone: 'brand',
            icon: 'target',
            sub: 'Trường hồ sơ đã điền',
            progress: completion,
            interactive: false,
        },
        {
            key: 'skill',
            label: 'Điểm kỹ năng',
            value: skillScore != null ? `${skillScore}/100` : profileDisplayValue(null),
            tone: 'amber',
            icon: 'talent-score',
            sub: skillScore != null ? 'Trung bình mức độ kỹ năng' : 'Chưa chấm mức độ',
            progress: skillScore ?? null,
            interactive: true,
            payload: 'skills',
        },
        {
            key: 'projects',
            label: 'Dự án tham gia',
            value: projects,
            tone: 'sky',
            icon: 'projects',
            sub: 'Dự án đang gán',
            interactive: true,
            payload: 'achievements',
        },
        {
            key: 'tasks',
            label: 'Công việc hoàn thành',
            value: tasksDone,
            tone: 'emerald',
            icon: 'done',
            sub: tasksTotal ? `${s.task_completion ?? 0}% trên ${tasksTotal} việc` : 'Chưa có việc được giao',
            progress: s.task_completion ?? null,
            interactive: true,
            payload: 'achievements',
        },
        {
            key: 'worklog',
            label: 'Giờ worklog',
            value: hours,
            tone: 'violet',
            icon: 'worklog',
            sub: 'Tổng giờ ghi nhận',
            interactive: false,
        },
    ];
});

const progressDenominator = computed(() => {
    const s = props.stats;
    if (s.task_completion != null && (s.tasks_total ?? 0) > 0) {
        return 100;
    }
    return 100;
});

function onSelect(card) {
    if (card.payload) {
        emit('go-tab', card.payload);
    }
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê hồ sơ cá nhân"
    heading="Chỉ số hồ sơ"
    hint="Thẻ có viền nét đứt — bấm để mở tab liên quan"
    :cards="cards"
    :active-key="activeTab === 'skills' ? 'skill' : activeTab === 'achievements' ? 'projects' : ''"
    :progress-denominator="progressDenominator"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
    @select="onSelect"
  />
</template>
