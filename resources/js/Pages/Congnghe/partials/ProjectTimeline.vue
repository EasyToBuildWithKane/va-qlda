<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { tone } from './tones.js';
import { useInView } from './motion.js';
import { openCongngheProject } from './useCongngheProjectModal.js';
import CongngheProjectGallery from './CongngheProjectGallery.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    phases: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.12 });

const heading = computed(() => props.content?.heading ?? {});
const PHASE_HINT = computed(() => props.content?.phase_hints ?? {});

const activePhaseIndex = ref(0);
const slideIndex = ref(0);
const slideDirection = ref(1);

const activePhase = computed(() => props.phases[activePhaseIndex.value] ?? props.phases[0] ?? null);
const slides = computed(() => activePhase.value?.items ?? []);
const slideCount = computed(() => slides.value.length);

const canPrev = computed(() => slideIndex.value > 0);
const canNext = computed(() => slideIndex.value < slideCount.value - 1);

watch(activePhaseIndex, () => {
    slideIndex.value = 0;
    slideDirection.value = 1;
});

function selectPhase(i) {
    if (i === activePhaseIndex.value) {
        return;
    }
    activePhaseIndex.value = i;
}

function goSlide(delta) {
    const next = slideIndex.value + delta;
    if (next < 0 || next >= slideCount.value) {
        return;
    }
    slideDirection.value = delta;
    slideIndex.value = next;
}

function goToSlide(i) {
    if (i === slideIndex.value || i < 0 || i >= slideCount.value) {
        return;
    }
    slideDirection.value = i > slideIndex.value ? 1 : -1;
    slideIndex.value = i;
}

function onKeydown(e) {
    const tag = e.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable) {
        return;
    }
    if (!sectionVisible.value || !slideCount.value) {
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
        goSlide(-1);
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        goSlide(1);
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
        <div
          v-if="slideCount"
          class="flex shrink-0 items-center gap-2"
        >
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition enabled:hover:border-brand/40 enabled:hover:bg-white/10 enabled:hover:text-white disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canPrev"
            aria-label="Slide trước"
            @click="goSlide(-1)"
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
            {{ slideIndex + 1 }} / {{ slideCount }}
          </span>
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition enabled:hover:border-brand/40 enabled:hover:bg-white/10 enabled:hover:text-white disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canNext"
            aria-label="Slide sau"
            @click="goSlide(1)"
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

      <!-- Lifecycle chapter rail -->
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
              type="button"
              role="tab"
              :aria-selected="activePhaseIndex === i"
              class="group relative flex min-w-0 flex-1 items-center gap-3 overflow-hidden rounded-2xl border px-4 py-3.5 text-left transition-all duration-300 sm:px-5"
              :class="activePhaseIndex === i
                ? 'border-white/25 bg-white/[0.08] shadow-[0_10px_40px_-16px_rgba(255,77,141,0.6)]'
                : 'border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.05]'"
              @click="selectPhase(i)"
            >
              <span
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl font-display text-sm font-bold ring-1 ring-inset transition-transform duration-300 group-hover:scale-105"
                :class="tone(phase.color).soft"
              >
                {{ i + 1 }}
              </span>
              <span class="min-w-0 flex-1">
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
            <div
              v-if="i < phases.length - 1"
              aria-hidden="true"
              class="hidden shrink-0 items-center justify-center sm:flex"
            >
              <svg
                class="text-white/20"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M9 6l6 6-6 6" /></svg>
            </div>
          </template>
        </div>
      </div>

      <!-- Slide stage -->
      <div
        v-if="activePhase"
        class="mt-8"
      >
        <div
          v-if="!slideCount"
          class="rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-20 text-center"
        >
          <p class="font-display text-lg font-semibold text-white/70">
            Chưa có dự án ở giai đoạn «{{ activePhase.label }}»
          </p>
          <p class="mx-auto mt-2 max-w-md text-sm text-white/45">
            {{ PHASE_HINT[activePhase.value] || 'Các sản phẩm mới sẽ xuất hiện tại đây khi được gán loại vòng đời tương ứng.' }}
          </p>
        </div>

        <div
          v-else
          class="relative"
        >
          <!-- Phase context strip -->
          <div
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 backdrop-blur-sm"
          >
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
            <p class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-white/35">
              Phím ← → để lướt
            </p>
          </div>

          <div class="relative overflow-hidden rounded-3xl ring-1 ring-white/10">
            <div
              class="flex transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
              :style="{ transform: `translateX(-${slideIndex * 100}%)` }"
            >
              <article
                v-for="(project, idx) in slides"
                :key="project.id"
                class="w-full shrink-0 px-0.5"
                :aria-hidden="idx !== slideIndex"
              >
                <GlassCard
                  tilt
                  :padded="false"
                  class="cn-slide min-h-[22rem] overflow-hidden p-0 sm:min-h-[20rem]"
                  :class="idx === slideIndex ? (slideDirection > 0 ? 'cn-slide--in-next' : 'cn-slide--in-prev') : ''"
                >
                  <div class="grid gap-0 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                    <!-- Visual panel -->
                    <div
                      class="relative flex flex-col justify-between border-b border-white/10 p-6 sm:p-8 lg:border-b-0 lg:border-r"
                      :style="{
                        background: `linear-gradient(135deg, ${project.color || '#9A0036'}22 0%, transparent 55%), rgba(255,255,255,0.02)`,
                      }"
                    >
                      <div class="flex items-start justify-end gap-4">
                        <span
                          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset"
                          :class="tone(project.statusColor).soft"
                        >
                          <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="tone(project.statusColor).dot"
                          />
                          {{ project.status }}
                        </span>
                      </div>

                      <div class="mt-6 lg:mt-8 lg:pt-4">
                        <p
                          v-if="project.code"
                          class="font-mono text-[11px] uppercase tracking-[0.2em] text-white/40"
                        >
                          {{ project.code }}
                        </p>
                        <h3 class="mt-2 font-display text-2xl font-bold leading-tight text-white sm:text-3xl">
                          {{ project.name }}
                        </h3>
                        <button
                          type="button"
                          class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-200 transition hover:text-[#ff4d8d]"
                          @click="openCongngheProject(project)"
                        >
                          Xem mô tả chi tiết
                          <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                          ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </button>
                        <p class="mt-4 max-w-xl text-sm leading-relaxed text-white/60 sm:text-[15px]">
                          {{ project.description || 'Chưa có mô tả cho dự án này.' }}
                        </p>
                      </div>

                      <div class="mt-8">
                        <div class="flex items-center justify-between font-mono text-[11px] text-white/55">
                          <span>TIẾN ĐỘ</span>
                          <span class="text-base font-semibold text-white">{{ project.progress }}%</span>
                        </div>
                        <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-white/10">
                          <div
                            class="h-full rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d,#9A0036)] bg-[length:200%_100%] animate-cn-shimmer"
                            :style="{ width: `${project.progress}%` }"
                          />
                        </div>
                      </div>
                    </div>

                    <!-- Gallery & meta -->
                    <div class="flex flex-col justify-between p-6 sm:p-8">
                      <CongngheProjectGallery
                        :key="project.id"
                        :images="project.images"
                      />

                      <div class="mt-6">
                        <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40">
                          Phụ trách chính
                        </p>
                        <div
                          v-if="project.manager"
                          class="mt-4 flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.04] p-4"
                        >
                          <Avatar
                            :name="project.manager.name"
                            :src="project.manager.avatar"
                            :size="52"
                          />
                          <div class="min-w-0 flex-1">
                            <p class="truncate font-display text-lg font-bold text-white">
                              {{ project.manager.name }}
                            </p>
                            <p class="mt-0.5 truncate text-sm text-white/50">
                              {{ project.manager.role_title || 'Phụ trách chính' }}
                            </p>
                          </div>
                        </div>
                        <div
                          v-else
                          class="mt-4 flex items-center gap-3 rounded-2xl border border-dashed border-white/15 bg-white/[0.02] p-4"
                        >
                          <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full border border-dashed border-white/15 text-white/30">
                            <svg
                              width="20"
                              height="20"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              stroke-width="2"
                            ><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M5 21v-2a5 5 0 0 1 5-5h4" /></svg>
                          </span>
                          <p class="text-sm text-white/45">
                            Chưa phân công phụ trách
                          </p>
                        </div>
                      </div>

                      <div class="mt-8 border-t border-white/10 pt-6">
                        <p class="text-[12px] text-white/45">
                          Slide {{ idx + 1 }} trong giai đoạn «{{ activePhase.label }}»
                        </p>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                          <button
                            v-for="(_, dotIdx) in slides"
                            :key="dotIdx"
                            type="button"
                            class="h-2 rounded-full transition-all duration-300"
                            :class="dotIdx === slideIndex
                              ? 'w-8 bg-[linear-gradient(110deg,#9A0036,#ff4d8d)]'
                              : 'w-2 bg-white/20 hover:bg-white/40'"
                            :aria-label="`Đến slide ${dotIdx + 1}`"
                            :aria-current="dotIdx === slideIndex ? 'true' : undefined"
                            @click="goToSlide(dotIdx)"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </GlassCard>
              </article>
            </div>
          </div>

          <!-- Thumbnail strip (quick jump) -->
          <div
            v-if="slideCount > 1"
            class="mt-5 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          >
            <button
              v-for="(project, idx) in slides"
              :key="`thumb-${project.id}`"
              type="button"
              class="flex min-w-[9.5rem] shrink-0 flex-col rounded-xl border px-3 py-2.5 text-left transition-all duration-200"
              :class="idx === slideIndex
                ? 'border-brand/50 bg-brand/10 shadow-[0_0_24px_-8px_rgba(154,0,54,0.8)]'
                : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.06]'"
              @click="goToSlide(idx)"
            >
              <span class="min-w-0">
                <span class="block truncate text-[12px] font-semibold text-white">{{ project.name }}</span>
                <span class="font-mono text-[10px] text-white/40">{{ project.progress }}%</span>
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cn-slide--in-next {
    animation: cn-slide-in-next 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.cn-slide--in-prev {
    animation: cn-slide-in-prev 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes cn-slide-in-next {
    from {
        opacity: 0.6;
        transform: translateX(12px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes cn-slide-in-prev {
    from {
        opacity: 0.6;
        transform: translateX(-12px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-slide--in-next,
    .cn-slide--in-prev {
        animation: none;
    }
}
</style>
