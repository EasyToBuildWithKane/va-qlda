<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import CountStat from './CountStat.vue';
import { useInView } from './motion.js';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.2 });

const stats = computed(() => {
    const m = props.metrics ?? {};
    const tasks = Number(m.tasks ?? 0);
    const done = Number(m.doneTasks ?? 0);
    const taskPct = tasks > 0 ? Math.round((done / tasks) * 100) : 0;

    return [
        {
            key: 'projects',
            label: 'Dự án triển khai',
            suffix: '+',
            tone: 'brand',
            icon: 'layers',
            sub: 'Đang và đã hoàn thành',
            progress: null,
        },
        {
            key: 'orgPeople',
            label: 'Nhân sự trên sơ đồ',
            suffix: '',
            tone: 'cyan',
            icon: 'people',
            sub: 'Phòng Công nghệ',
            progress: null,
        },
        {
            key: 'doneTasks',
            label: 'Công việc hoàn thành',
            suffix: '+',
            tone: 'emerald',
            icon: 'check',
            sub: tasks ? `${taskPct}% tổng task` : 'Theo dữ liệu QLDA',
            progress: taskPct,
        },
        {
            key: 'departments',
            label: 'Phòng ban đồng hành',
            suffix: '',
            tone: 'violet',
            icon: 'building',
            sub: 'Liên phòng ban',
            progress: null,
        },
        {
            key: 'orgTeams',
            label: 'Nhóm tổ chức',
            suffix: '',
            tone: 'amber',
            icon: 'teams',
            sub: 'Nhánh & đơn vị',
            progress: null,
        },
        {
            key: 'aiAccounts',
            label: 'Tài khoản AI',
            suffix: '',
            tone: 'rose',
            icon: 'ai',
            sub: 'Được quản lý tập trung',
            progress: null,
        },
    ];
});

const toneMap = {
    brand: {
        card: 'from-brand/25 via-brand/5 to-transparent border-brand/30 ring-brand/20',
        icon: 'bg-brand/20 text-brand-200 ring-brand/30',
        bar: 'from-brand to-[#ff4d8d]',
        num: 'text-white',
    },
    cyan: {
        card: 'from-cyan-500/20 via-cyan-500/5 to-transparent border-cyan-400/25 ring-cyan-400/15',
        icon: 'bg-cyan-500/15 text-cyan-200 ring-cyan-400/25',
        bar: 'from-cyan-400 to-brand',
        num: 'text-white',
    },
    emerald: {
        card: 'from-emerald-500/20 via-emerald-500/5 to-transparent border-emerald-400/25 ring-emerald-400/15',
        icon: 'bg-emerald-500/15 text-emerald-200 ring-emerald-400/25',
        bar: 'from-emerald-400 to-cyan-400',
        num: 'text-white',
    },
    violet: {
        card: 'from-violet-500/20 via-violet-500/5 to-transparent border-violet-400/25 ring-violet-400/15',
        icon: 'bg-violet-500/15 text-violet-200 ring-violet-400/25',
        bar: 'from-violet-400 to-brand',
        num: 'text-white',
    },
    amber: {
        card: 'from-amber-500/20 via-amber-500/5 to-transparent border-amber-400/25 ring-amber-400/15',
        icon: 'bg-amber-500/15 text-amber-100 ring-amber-400/25',
        bar: 'from-amber-400 to-brand',
        num: 'text-white',
    },
    rose: {
        card: 'from-rose-500/20 via-rose-500/5 to-transparent border-rose-400/25 ring-rose-400/15',
        icon: 'bg-rose-500/15 text-rose-200 ring-rose-400/25',
        bar: 'from-rose-400 to-violet-400',
        num: 'text-white',
    },
};

function valueOf(key) {
    return Number(props.metrics?.[key] ?? 0);
}

function toneOf(tone) {
    return toneMap[tone] ?? toneMap.brand;
}
</script>

<template>
  <section
    id="thanh-tuu"
    ref="target"
    class="relative py-20"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-cyan-400/30 to-transparent"
      aria-hidden="true"
    />
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-wrap items-end justify-between gap-6">
        <SectionHeading
          eyebrow="Thành tựu nổi bật"
          title="Những con số biết nói"
          subtitle="Tổng hợp trực tiếp từ dữ liệu vận hành — cập nhật theo thời gian thực."
        />
        <div class="hidden items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-3 py-1.5 font-mono text-[11px] text-emerald-300 sm:flex">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-cn-glow" />
          LIVE DATA
        </div>
      </div>

      <div class="mt-12 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <article
          v-for="(s, i) in stats"
          :key="s.key"
          class="cn-metric-card group relative overflow-hidden rounded-2xl border bg-gradient-to-br p-5 ring-1 transition-all duration-700 sm:p-6"
          :class="[
            toneOf(s.tone).card,
            shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
          ]"
          :style="{ transitionDelay: `${i * 70}ms` }"
        >
          <div
            class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/[0.04] blur-2xl transition group-hover:bg-white/[0.07]"
            aria-hidden="true"
          />
          <div class="flex items-start justify-between gap-2">
            <span
              class="grid h-10 w-10 shrink-0 place-items-center rounded-xl ring-1"
              :class="toneOf(s.tone).icon"
            >
              <svg
                v-if="s.icon === 'layers'"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              ><path d="M12 2 2 7l10 5 10-5-10-5ZM2 17l10 5 10-5M2 12l10 5 10-5" /></svg>
              <svg
                v-else-if="s.icon === 'people'"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              ><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle
                cx="9"
                cy="7"
                r="4"
              /><path d="M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75" /></svg>
              <svg
                v-else-if="s.icon === 'check'"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M20 6 9 17l-5-5" /></svg>
              <svg
                v-else-if="s.icon === 'building'"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              ><path d="M3 21V7l9-4 9 4v14M9 21v-6h6v6" /></svg>
              <svg
                v-else-if="s.icon === 'teams'"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              ><path d="M12 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM4 22a8 8 0 0 1 16 0" /></svg>
              <svg
                v-else
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              ><path d="M12 2a5 5 0 0 1 5 5v1a5 5 0 0 1-1 9H8a5 5 0 0 1-1-9V7a5 5 0 0 1 5-5Z" /></svg>
            </span>
            <span class="font-mono text-[10px] tabular-nums text-white/25">0{{ i + 1 }}</span>
          </div>

          <p
            class="mt-4 font-display text-3xl font-extrabold tabular-nums sm:text-4xl"
            :class="toneOf(s.tone).num"
          >
            <CountStat
              :value="valueOf(s.key)"
              :active="shown"
            /><span class="text-lg text-brand-200/90 sm:text-xl">{{ s.suffix }}</span>
          </p>
          <p class="mt-1.5 text-sm font-semibold text-white/90">
            {{ s.label }}
          </p>
          <p class="mt-0.5 text-[11px] text-white/45">
            {{ s.sub }}
          </p>

          <div
            v-if="s.progress != null && s.progress > 0"
            class="mt-4 h-1 overflow-hidden rounded-full bg-white/10"
          >
            <div
              class="h-full rounded-full bg-gradient-to-r transition-all duration-1000"
              :class="toneOf(s.tone).bar"
              :style="{ width: shown ? `${s.progress}%` : '0%' }"
            />
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cn-metric-card {
    clip-path: polygon(0 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%);
}

@media (prefers-reduced-motion: reduce) {
    .cn-metric-card {
        transition: none;
    }
}
</style>
