<script setup>
import { computed } from 'vue';
import { Radar } from 'vue-chartjs';
import {
    Chart as ChartJS, RadialLinearScale, PointElement, LineElement, Filler, Tooltip,
} from 'chart.js';
import EmptyState from '@/shared/ui/EmptyState.vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip);

const props = defineProps({
    radar: { type: Array, default: () => [] },
});

const hasData = computed(() => props.radar.some((r) => r.count > 0));

const chartData = computed(() => ({
    labels: props.radar.map((r) => r.label),
    datasets: [{
        label: 'Điểm năng lực',
        data: props.radar.map((r) => r.score),
        backgroundColor: 'rgba(154, 0, 54, 0.14)',
        borderColor: '#9a0036',
        borderWidth: 2,
        pointBackgroundColor: '#9a0036',
        pointBorderColor: '#fff',
        pointRadius: 3,
        pointHoverRadius: 5,
        fill: true,
    }],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            min: 0,
            max: 100,
            ticks: { stepSize: 25, font: { size: 9 }, color: '#94a3b8', backdropColor: 'transparent' },
            grid: { color: 'rgba(148, 163, 184, 0.25)' },
            angleLines: { color: 'rgba(148, 163, 184, 0.25)' },
            pointLabels: { font: { size: 10.5, weight: '600' }, color: '#475569' },
        },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const item = props.radar[ctx.dataIndex] ?? {};
                    return `${ctx.parsed.r}/100 · ${item.count ?? 0} kỹ năng`;
                },
            },
        },
    },
};

const collapsedBadge = computed(() => {
    const n = props.radar.filter((r) => r.count > 0).length;
    return n ? `${n} nhóm` : null;
});
</script>

<template>
  <ProfileInfoPanel
    title="Bản đồ năng lực"
    icon="performance"
    subtitle="Điểm trung bình theo nhóm kỹ năng"
    section-key="profile-skill-radar"
    :collapsed-badge="collapsedBadge"
  >
    <div class="p-5">
      <div
        v-if="hasData"
        class="relative h-64"
      >
        <Radar
          :data="chartData"
          :options="options"
        />
      </div>
      <EmptyState
        v-else
        icon="performance"
        title="Chưa đủ dữ liệu"
        description="Chưa đủ dữ liệu kỹ năng để dựng bản đồ năng lực."
      />
    </div>
  </ProfileInfoPanel>
</template>
