<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import CountStat from './CountStat.vue';
import AnalyzingBadge from './AnalyzingBadge.vue';
import DataStreamTicker from './DataStreamTicker.vue';
import { useInView } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.2 });

const heading = computed(() => props.content?.heading ?? {});
const liveBadge = computed(() => props.content?.live_badge ?? 'dữ liệu trực tiếp');

const streamItems = computed(() => {
    const m = props.metrics ?? {};
    const tasks = Number(m.tasks ?? 0);
    const done = Number(m.doneTasks ?? 0);
    const pct = tasks > 0 ? Math.round((done / tasks) * 100) : 0;
    return [
        `${m.activeProjects ?? 0} dự án đang chạy`,
        `${m.completedProjects ?? 0} dự án hoàn thành`,
        `${pct}% công việc đã xong`,
        `${m.departments ?? 0} phòng ban phối hợp`,
        `${m.orgTeams ?? 0} đội nhóm`,
        `${m.aiAccounts ?? 0} tài khoản AI`,
    ];
});

const stats = computed(() => {
    const m = props.metrics ?? {};
    const tasks = Number(m.tasks ?? 0);
    const done = Number(m.doneTasks ?? 0);
    const taskPct = tasks > 0 ? Math.round((done / tasks) * 100) : 0;

    return (props.content?.stats ?? []).map((s) => {
        // doneTasks hiển thị % tự tính theo dữ liệu thật khi có công việc.
        const isTaskPct = s.key === 'doneTasks';

        return {
            key: s.key,
            label: s.label,
            suffix: s.suffix ?? '',
            tone: s.tone ?? 'brand',
            sub: isTaskPct && tasks ? `${taskPct}% tổng task` : (s.sub ?? ''),
            progress: isTaskPct ? taskPct : null,
        };
    });
});

const toneMap = {
    brand: {
        accent: 'text-brand-200',
        bar: 'from-brand to-[#ff4d8d]',
        dot: 'bg-brand',
    },
    cyan: {
        accent: 'text-cyan-200',
        bar: 'from-cyan-400 to-brand',
        dot: 'bg-cyan-400',
    },
    emerald: {
        accent: 'text-emerald-200',
        bar: 'from-emerald-400 to-cyan-400',
        dot: 'bg-emerald-400',
    },
    violet: {
        accent: 'text-violet-200',
        bar: 'from-violet-400 to-brand',
        dot: 'bg-violet-400',
    },
    amber: {
        accent: 'text-amber-100',
        bar: 'from-amber-400 to-brand',
        dot: 'bg-amber-400',
    },
    rose: {
        accent: 'text-rose-200',
        bar: 'from-rose-400 to-violet-400',
        dot: 'bg-rose-400',
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
    class="relative scroll-mt-24 py-16 sm:scroll-mt-28 sm:py-20 md:py-24"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-cyan-400/30 to-transparent"
      aria-hidden="true"
    />
    <div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <SectionHeading
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />
        <AnalyzingBadge
          v-if="liveBadge"
          class="hidden shrink-0 sm:inline-flex"
          :label="liveBadge"
          tone="emerald"
        />
      </div>

      <!-- Một hàng trên desktop; mobile cuộn ngang -->
      <div
        class="cn-metrics-strip relative mt-10 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] ring-1 ring-white/5 transition-all duration-700"
        :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
      >
        <div
          class="pointer-events-none absolute inset-0 z-0 animate-cn-pulse-grid"
          style="
            background-image:
              linear-gradient(to right, rgba(255,255,255,0.05) 1px, transparent 1px),
              linear-gradient(to bottom, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 26px 26px;
          "
          aria-hidden="true"
        />
        <div
          class="relative z-10 flex snap-x snap-mandatory gap-0 overflow-x-auto overscroll-x-contain lg:grid lg:grid-cols-6 lg:overflow-visible lg:snap-none"
          role="list"
        >
          <article
            v-for="(s, i) in stats"
            :key="s.key"
            role="listitem"
            class="cn-metric-cell group relative min-w-[42%] shrink-0 snap-start border-r border-white/[0.06] p-3.5 last:border-r-0 sm:min-w-[30%] sm:p-4 lg:min-w-0 lg:p-3 lg:last:border-r-0 xl:p-4"
            :class="shown ? 'opacity-100' : 'opacity-0'"
            :style="{ transitionDelay: `${i * 50}ms` }"
          >
            <span
              class="pointer-events-none absolute left-0 top-0 h-0.5 w-full scale-x-0 bg-gradient-to-r opacity-80 transition-transform duration-500 group-hover:scale-x-100"
              :class="toneOf(s.tone).bar"
              aria-hidden="true"
            />
            <span
              class="mb-2 inline-block h-1 w-1 rounded-full opacity-70"
              :class="toneOf(s.tone).dot"
              aria-hidden="true"
            />

            <p class="flex items-baseline gap-0.5 whitespace-nowrap font-display text-[1.65rem] font-extrabold leading-none tabular-nums text-white sm:text-3xl lg:text-[1.75rem] xl:text-[2rem]">
              <CountStat
                :value="valueOf(s.key)"
                :active="shown"
              /><span
                v-if="s.suffix"
                class="text-base font-bold sm:text-lg"
                :class="toneOf(s.tone).accent"
              >{{ s.suffix }}</span>
            </p>

            <p
              class="mt-1.5 truncate text-[11px] font-semibold leading-tight text-white/90 sm:text-xs"
              :title="s.label"
            >
              {{ s.label }}
            </p>
            <p
              class="mt-0.5 truncate text-[10px] leading-tight text-white/45"
              :title="s.sub"
            >
              {{ s.sub }}
            </p>

            <div
              v-if="s.progress != null && s.progress > 0"
              class="mt-2.5 h-0.5 overflow-hidden rounded-full bg-white/10"
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

      <!-- Dòng dữ liệu cuộn liên tục -->
      <DataStreamTicker
        class="mt-5"
        :items="streamItems"
        variant="marquee"
      />
    </div>
  </section>
</template>

<style scoped>
.cn-metrics-strip {
    clip-path: polygon(0 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%);
}

.cn-metric-cell {
    transition: opacity 0.5s ease, transform 0.5s ease;
}

@media (prefers-reduced-motion: reduce) {
    .cn-metrics-strip,
    .cn-metric-cell {
        transition: none;
    }
}
</style>
