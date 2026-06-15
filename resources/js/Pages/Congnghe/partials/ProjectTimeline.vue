<script setup>
import { computed, ref } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { tone } from './tones.js';
import { useInView } from './motion.js';

const props = defineProps({
    phases: { type: Array, default: () => [] },
});

const { target, shown } = useInView({ threshold: 0.12 });

const active = ref(0);
const activePhase = computed(() => props.phases[active.value] ?? props.phases[0] ?? null);

function selectPhase(i) {
    active.value = i;
}

function initials(project) {
    return (project.code || project.name || '?').slice(0, 2).toUpperCase();
}
</script>

<template>
  <section
    id="du-an"
    ref="target"
    class="relative overflow-hidden py-28"
  >
    <!-- AI ambient glow -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 -z-0"
    >
      <div class="absolute left-[8%] top-24 h-72 w-72 rounded-full bg-brand/15 blur-[120px] animate-cn-float" />
      <div class="absolute right-[10%] bottom-10 h-64 w-64 rounded-full bg-violet-500/12 blur-[120px] animate-cn-float-x" />
    </div>

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Vòng đời sản phẩm số"
        title="Hành trình của mỗi sản phẩm"
        subtitle="Ba giai đoạn cốt lõi — nghiên cứu phát triển, triển khai nghiệm thu và vận hành cải tiến. Chọn một giai đoạn để xem các dự án và người phụ trách chính."
      />

      <!-- Tabs -->
      <div
        class="mt-10 flex flex-wrap gap-2.5 transition-all duration-700"
        :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
      >
        <button
          v-for="(phase, i) in phases"
          :key="phase.value"
          type="button"
          class="group relative flex items-center gap-2.5 overflow-hidden rounded-2xl border px-4 py-3 text-left transition-all duration-300 sm:px-5"
          :class="active === i
            ? 'border-white/25 bg-white/[0.08] shadow-[0_10px_40px_-16px_rgba(255,77,141,0.6)]'
            : 'border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.05]'"
          @click="selectPhase(i)"
        >
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl font-display text-sm font-bold ring-1 ring-inset transition-transform duration-300 group-hover:scale-105"
            :class="tone(phase.color).soft"
          >
            {{ i + 1 }}
          </span>
          <span class="min-w-0">
            <span class="block truncate font-display text-sm font-bold text-white">{{ phase.label }}</span>
            <span class="mt-0.5 flex items-center gap-1.5 font-mono text-[11px] text-white/45">
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="tone(phase.color).dot"
              />
              {{ phase.total }} dự án
            </span>
          </span>
          <!-- active underline sweep -->
          <span
            class="pointer-events-none absolute inset-x-3 bottom-0 h-0.5 origin-left rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d)] transition-transform duration-300"
            :class="active === i ? 'scale-x-100' : 'scale-x-0'"
          />
        </button>
      </div>

      <!-- Project cards for the active phase -->
      <div
        v-if="activePhase"
        :key="activePhase.value"
        class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3"
      >
        <GlassCard
          v-for="(project, idx) in activePhase.items"
          :key="project.id"
          tilt
          :padded="false"
          class="cn-card group/card flex flex-col p-6"
          :style="{ animationDelay: `${idx * 75}ms` }"
        >
          <!-- header -->
          <div class="flex items-start justify-between gap-3">
            <span
              class="relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl font-display text-base font-bold text-white shadow-lg ring-1 ring-white/20"
              :style="{ backgroundColor: project.color || '#9A0036' }"
            >
              {{ initials(project) }}
              <span
                class="absolute inset-0 rounded-2xl opacity-60 blur-md"
                :style="{ backgroundColor: project.color || '#9A0036' }"
              />
            </span>
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
              :class="tone(project.statusColor).soft"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="tone(project.statusColor).dot"
              />
              {{ project.status }}
            </span>
          </div>

          <!-- name + description -->
          <h3 class="mt-5 font-display text-lg font-bold leading-snug text-white">
            {{ project.name }}
          </h3>
          <p
            v-if="project.code"
            class="mt-1 font-mono text-[11px] uppercase tracking-wide text-white/35"
          >
            {{ project.code }}
          </p>
          <p
            class="mt-3 text-[13px] leading-relaxed text-white/55"
            style="display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;"
          >
            {{ project.description || 'Chưa có mô tả cho dự án này.' }}
          </p>

          <!-- progress -->
          <div class="mt-5">
            <div class="flex items-center justify-between font-mono text-[11px] text-white/55">
              <span>TIẾN ĐỘ</span>
              <span class="font-semibold text-white">{{ project.progress }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
              <div
                class="h-full rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d,#9A0036)] bg-[length:200%_100%] animate-cn-shimmer"
                :style="{ width: `${project.progress}%` }"
              />
            </div>
          </div>

          <!-- manager -->
          <div class="mt-5 flex items-center gap-3 border-t border-white/10 pt-4">
            <template v-if="project.manager">
              <Avatar
                :name="project.manager.name"
                :src="project.manager.avatar"
                :size="36"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-[13px] font-semibold text-white">
                  {{ project.manager.name }}
                </p>
                <p class="truncate text-[11.5px] text-white/45">
                  {{ project.manager.role_title || 'Phụ trách chính' }}
                </p>
              </div>
              <span class="shrink-0 rounded-full border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-[9.5px] uppercase tracking-wider text-white/45">
                Phụ trách
              </span>
            </template>
            <template v-else>
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-dashed border-white/15 text-white/30">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                ><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M5 21v-2a5 5 0 0 1 5-5h4" /></svg>
              </span>
              <p class="text-[12.5px] text-white/40">
                Chưa phân công phụ trách
              </p>
            </template>
          </div>
        </GlassCard>

        <!-- empty -->
        <p
          v-if="!activePhase.items.length"
          class="col-span-full rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center text-sm text-white/45"
        >
          Chưa có dự án nào ở giai đoạn “{{ activePhase.label }}”.
        </p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cn-card {
    animation: cn-card-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes cn-card-in {
    from {
        opacity: 0;
        transform: translateY(22px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-card {
        animation: none;
    }
}
</style>
