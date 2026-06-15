<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import CountStat from './CountStat.vue';
import GlassCard from './GlassCard.vue';
import { useInView } from './motion.js';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.25 });

const stats = [
    { key: 'projects', label: 'Dự án đã & đang triển khai', suffix: '+', icon: 'M3 7h18M3 12h18M3 17h18' },
    { key: 'orgPeople', label: 'Nhân sự trên sơ đồ', suffix: '', icon: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 .01' },
    { key: 'doneTasks', label: 'Công việc đã hoàn thành', suffix: '+', icon: 'M20 6 9 17l-5-5' },
    { key: 'departments', label: 'Phòng ban đồng hành', suffix: '', icon: 'M3 21V7l9-4 9 4v14M9 21v-6h6v6' },
    { key: 'orgTeams', label: 'Nhóm trong sơ đồ tổ chức', suffix: '', icon: 'M12 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM4 22a8 8 0 0 1 16 0' },
    { key: 'aiAccounts', label: 'Tài khoản AI được quản lý', suffix: '', icon: 'M12 2a5 5 0 0 1 5 5v1a5 5 0 0 1-1 9H8a5 5 0 0 1-1-9V7a5 5 0 0 1 5-5Z' },
];

const completion = computed(() => {
    const t = Number(props.metrics?.tasks ?? 0);
    const d = Number(props.metrics?.doneTasks ?? 0);
    return t > 0 ? Math.round((d / t) * 100) : 0;
});

function valueOf(key) {
    return Number(props.metrics?.[key] ?? 0);
}
</script>

<template>
  <section
    id="thanh-tuu"
    ref="target"
    class="relative py-20"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-wrap items-end justify-between gap-6">
        <SectionHeading
          eyebrow="Thành tựu nổi bật"
          title="Những con số biết nói"
          subtitle="Tổng hợp trực tiếp từ dữ liệu vận hành — cập nhật theo thời gian thực."
        />
        <div class="hidden items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 font-mono text-[11px] text-emerald-300 sm:flex">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-cn-glow" />
          LIVE · {{ completion }}% TASK DONE
        </div>
      </div>

      <div class="mt-14 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3">
        <GlassCard
          v-for="(s, i) in stats"
          :key="s.key"
          tilt
          :padded="false"
          class="p-6 transition-all duration-700 sm:p-7"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
          :style="{ transitionDelay: `${i * 80}ms` }"
        >
          <div class="flex items-center justify-between">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/5 text-brand-300 ring-1 ring-white/10">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              ><path :d="s.icon" /></svg>
            </span>
            <span class="font-mono text-[10px] uppercase tracking-wider text-white/30">0{{ i + 1 }}</span>
          </div>
          <div class="mt-5 font-display text-4xl font-extrabold text-white sm:text-5xl">
            <CountStat
              :value="valueOf(s.key)"
              :active="shown"
            /><span class="bg-gradient-to-r from-brand to-[#ff4d8d] bg-clip-text text-transparent">{{ s.suffix }}</span>
          </div>
          <p class="mt-2.5 text-[13px] leading-snug text-white/55">
            {{ s.label }}
          </p>
        </GlassCard>
      </div>
    </div>
  </section>
</template>
