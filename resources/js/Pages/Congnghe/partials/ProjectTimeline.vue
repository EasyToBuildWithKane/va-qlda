<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import SectionHeading from './SectionHeading.vue';
import AnalyzingBadge from './AnalyzingBadge.vue';
import DataStreamTicker from './DataStreamTicker.vue';
import RevealOnScroll from './RevealOnScroll.vue';
import ProjectShowcaseCard from './ProjectShowcaseCard.vue';
import { tone } from './tones.js';
import { useInView, useMagneticGroup } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    phases: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.12 });
const { register } = useMagneticGroup({ strength: 0.12 });

const heading = computed(() => props.content?.heading ?? {});
const PHASE_HINT = computed(() => props.content?.phase_hints ?? {});
const phaseStream = computed(() => props.phases.map((p) => `${p.label}: ${p.total} dự án`));

const activePhaseIndex = ref(0);

const activePhase = computed(() => props.phases[activePhaseIndex.value] ?? props.phases[0] ?? null);
const items = computed(() => activePhase.value?.items ?? []);
const featuredItem = computed(() => items.value[0] ?? null);
const restItems = computed(() => items.value.slice(1));

const canPrev = computed(() => activePhaseIndex.value > 0);
const canNext = computed(() => activePhaseIndex.value < props.phases.length - 1);

function selectPhase(i) {
    if (i < 0 || i >= props.phases.length || i === activePhaseIndex.value) {
        return;
    }
    activePhaseIndex.value = i;
}

function goPhase(delta) {
    selectPhase(activePhaseIndex.value + delta);
}

function onKeydown(e) {
    const tag = e.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable) {
        return;
    }
    if (!sectionVisible.value) {
        return;
    }
    const el = target.value;
    if (!el) {
        return;
    }
    const rect = el.getBoundingClientRect();
    const inView = rect.top < window.innerHeight * 0.85 && rect.bottom > window.innerHeight * 0.15;
    if (!inView) {
        return;
    }
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        goPhase(-1);
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        goPhase(1);
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
  <section
    id="du-an"
    ref="target"
    class="relative overflow-hidden py-20"
    tabindex="-1"
  >
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 -z-0"
    >
      <div class="absolute left-[8%] top-24 h-72 w-72 rounded-full bg-brand/15 blur-[120px] animate-cn-float" />
      <div class="absolute right-[10%] bottom-10 h-64 w-64 rounded-full bg-violet-500/12 blur-[120px] animate-cn-float-x" />
    </div>

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-wrap items-end justify-between gap-6">
        <SectionHeading
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />
        <div class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition enabled:hover:border-brand/40 enabled:hover:bg-white/10 enabled:hover:text-white disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canPrev"
            aria-label="Giai đoạn trước"
            @click="goPhase(-1)"
          >
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M15 6l-6 6 6 6" /></svg>
          </button>
          <span class="min-w-[4.5rem] text-center font-mono text-[11px] tabular-nums text-white/50">
            {{ activePhaseIndex + 1 }} / {{ phases.length }}
          </span>
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition enabled:hover:border-brand/40 enabled:hover:bg-white/10 enabled:hover:text-white disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canNext"
            aria-label="Giai đoạn sau"
            @click="goPhase(1)"
          >
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M9 6l6 6-6 6" /></svg>
          </button>
        </div>
      </div>

      <DataStreamTicker
        v-if="phaseStream.length"
        class="mt-5"
        :items="phaseStream"
        variant="marquee"
        :speed="30"
      />

      <!-- Stepper dòng chảy vòng đời -->
      <div
        class="mt-10 transition-all duration-700"
        :class="sectionVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
        role="tablist"
        aria-label="Giai đoạn vòng đời"
      >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
          <template
            v-for="(phase, i) in phases"
            :key="phase.value"
          >
            <button
              :ref="(el) => register(el)"
              type="button"
              role="tab"
              :aria-selected="activePhaseIndex === i"
              class="group/step relative flex min-w-0 flex-1 items-center gap-3 overflow-hidden rounded-2xl border px-4 py-3.5 text-left transition-all duration-300 will-change-transform sm:px-5"
              :class="activePhaseIndex === i
                ? 'border-white/25 bg-white/[0.08] shadow-[0_10px_40px_-16px_rgba(255,77,141,0.6)]'
                : 'border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.05]'"
              @click="selectPhase(i)"
            >
              <!-- Lưới pulse khi đang chọn -->
              <span
                v-if="activePhaseIndex === i"
                class="pointer-events-none absolute inset-0 animate-cn-pulse-grid"
                style="
                  background-image:
                    linear-gradient(rgba(255,77,141,0.10) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,77,141,0.10) 1px, transparent 1px);
                  background-size: 22px 22px;
                "
                aria-hidden="true"
              />
              <span
                class="relative grid h-10 w-10 shrink-0 place-items-center rounded-xl font-display text-sm font-bold ring-1 ring-inset transition-transform duration-300 group-hover/step:scale-105"
                :class="tone(phase.color).soft"
              >
                {{ i + 1 }}
              </span>
              <span class="relative min-w-0 flex-1">
                <span class="block truncate font-display text-sm font-bold text-white">{{ phase.label }}</span>
                <span class="mt-0.5 flex items-center gap-1.5 font-mono text-[11px] text-white/45">
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="tone(phase.color).dot"
                  />
                  {{ phase.total }} dự án
                </span>
              </span>
              <span
                class="pointer-events-none absolute inset-x-3 bottom-0 h-0.5 origin-left rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d)] transition-transform duration-300"
                :class="activePhaseIndex === i ? 'scale-x-100' : 'scale-x-0'"
              />
            </button>

            <!-- Đường dòng chảy giữa các bước -->
            <div
              v-if="i < phases.length - 1"
              aria-hidden="true"
              class="hidden shrink-0 items-center gap-1 px-1 sm:flex"
            >
              <span class="h-px w-6 rounded-full bg-[linear-gradient(90deg,rgba(255,255,255,0.08),rgba(255,77,141,0.55),rgba(255,255,255,0.08))] bg-[length:200%_100%] animate-cn-shimmer" />
              <svg
                class="text-brand-300/60"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M9 6l6 6-6 6" /></svg>
            </div>
          </template>
        </div>
      </div>

      <!-- Nội dung giai đoạn -->
      <div
        v-if="activePhase"
        class="mt-8"
      >
        <!-- Dải ngữ cảnh -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 backdrop-blur-sm">
          <div class="flex min-w-0 items-center gap-2.5">
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
              :class="tone(activePhase.color).soft"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="tone(activePhase.color).dot"
              />
              {{ activePhase.label }}
            </span>
            <p class="truncate text-[12.5px] text-white/50">
              {{ PHASE_HINT[activePhase.value] }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-3">
            <AnalyzingBadge
              label="AI theo dõi tiến độ"
              tone="violet"
            />
            <p class="hidden font-mono text-[10px] uppercase tracking-wider text-white/35 sm:block">
              Phím ← → để đổi giai đoạn
            </p>
          </div>
        </div>

        <!-- Lưới dự án (đồng nhất với Hệ sinh thái sản phẩm) -->
        <div
          v-if="items.length"
          :key="activePhase.value"
        >
          <RevealOnScroll
            v-if="featuredItem"
            variant="up"
            class="block"
          >
            <ProjectShowcaseCard
              :project="featuredItem"
              featured
            />
          </RevealOnScroll>

          <div
            v-if="restItems.length"
            class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
          >
            <RevealOnScroll
              v-for="(project, idx) in restItems"
              :key="project.id"
              variant="up"
              :delay="80 + (idx % 3) * 90"
              class="block h-full"
            >
              <ProjectShowcaseCard :project="project" />
            </RevealOnScroll>
          </div>
        </div>

        <!-- Trống -->
        <div
          v-else
          class="rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-20 text-center"
        >
          <p class="font-display text-lg font-semibold text-white/70">
            Chưa có dự án ở giai đoạn «{{ activePhase.label }}»
          </p>
          <p class="mx-auto mt-2 max-w-md text-sm text-white/45">
            {{ PHASE_HINT[activePhase.value] || 'Các sản phẩm mới sẽ xuất hiện tại đây khi được gán loại vòng đời tương ứng.' }}
          </p>
        </div>
      </div>
    </div>
  </section>
</template>
