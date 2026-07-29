<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
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
            label: 'Hồ sơ đã điền',
            value: `${completion}%`,
            tone: 'brand',
            icon: 'target',
            sub: completion >= 80 ? 'Gần đủ thông tin' : 'Còn một số mục trống',
            progress: completion,
            interactive: false,
        },
        {
            key: 'skill',
            label: 'Năng lực kỹ năng',
            value: skillScore != null ? `${skillScore} điểm` : profileDisplayValue(null),
            tone: 'amber',
            icon: 'talent-score',
            sub: skillScore != null ? 'Theo mức tự đánh giá' : 'Chưa tự đánh giá kỹ năng',
            progress: skillScore ?? null,
            interactive: true,
            payload: 'skills',
        },
        {
            key: 'projects',
            label: 'Dự án đang làm',
            value: projects,
            tone: 'sky',
            icon: 'projects',
            sub: projects === 1 ? '1 dự án' : `${projects} dự án`,
            interactive: true,
            payload: 'achievements',
        },
        {
            key: 'tasks',
            label: 'Việc đã xong',
            value: tasksDone,
            tone: 'emerald',
            icon: 'done',
            sub: tasksTotal
                ? `${s.task_completion ?? 0}% trong ${tasksTotal} việc được giao`
                : 'Chưa được giao việc',
            progress: s.task_completion ?? null,
            interactive: true,
            payload: 'achievements',
        },
        {
            key: 'worklog',
            label: 'Giờ đã ghi nhận',
            value: hours,
            tone: 'violet',
            icon: 'worklog',
            sub: hours === 0 ? 'Chưa ghi giờ làm' : 'Tổng giờ làm trên hệ thống',
            interactive: false,
        },
    ];
});

const progressDenominator = computed(() => 100);

const completionBadge = computed(() => {
    const c = props.stats.profile_completion ?? 0;
    return `${c}% đã điền`;
});

function onSelect(card) {
    if (card.payload) {
        emit('go-tab', card.payload);
    }
}
</script>

<template>
  <ProfileInfoPanel
    title="Tóm tắt của bạn"
    icon="target"
    subtitle="Bấm thẻ viền nét đứt để xem chi tiết liên quan"
    section-key="profile-overview-kpi"
    :collapsed-badge="completionBadge"
  >
    <KpiSummaryStrip
      hide-header
      aria-label="Tóm tắt hồ sơ cá nhân"
      heading="Tóm tắt của bạn"
      hint=""
      eyebrow="Tóm tắt"
      shell-class="kpi-strip relative mb-0 overflow-x-hidden border-0 bg-transparent px-4 py-4 shadow-none sm:px-5 sm:py-4"
      :cards="cards"
      :active-key="activeTab === 'skills' ? 'skill' : activeTab === 'achievements' ? 'projects' : ''"
      :progress-denominator="progressDenominator"
      grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
      @select="onSelect"
    />
  </ProfileInfoPanel>
</template>
