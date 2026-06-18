<script setup>
import { computed, inject, ref } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView, useScrollScene } from './motion.js';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';
import SectionParticleNetwork from './SectionParticleNetwork.vue';
import CongngheBrandImage from './CongngheBrandImage.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.1 });
const drawProgress = useScrollScene(target, { start: 0.08, end: 0.65 });

const icons = inject('congngheIcons', {});

const heading = computed(() => props.content?.heading ?? {});
const milestones = computed(() => props.content?.milestones ?? []);
const guideLabel = computed(() => props.content?.guide_label ?? '');
const companionNote = computed(() => props.content?.companion_note ?? '');

// ── Trạng thái dẫn xuất từ live + progress (admin tinh chỉnh được) ──────
const STATUS = {
    active: {
        ring: 'ring-emerald-400/50',
        badge: 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300',
        bar: 'from-emerald-400 to-emerald-300',
        stroke: 'stroke-emerald-400',
        node: 'border-emerald-400/60 bg-emerald-400/10 text-emerald-200',
    },
    done: {
        ring: 'ring-brand/50',
        badge: 'border-brand/30 bg-brand/10 text-brand-200',
        bar: 'from-brand to-[#ff4d8d]',
        stroke: 'stroke-brand',
        node: 'border-brand/60 bg-brand/15 text-brand-100',
    },
    progress: {
        ring: 'ring-amber-400/40',
        badge: 'border-amber-400/20 bg-amber-400/10 text-amber-300',
        bar: 'from-amber-400 to-amber-300',
        stroke: 'stroke-amber-400',
        node: 'border-amber-400/50 bg-amber-400/10 text-amber-200',
    },
    planned: {
        ring: 'ring-white/15',
        badge: 'border-white/10 bg-white/5 text-white/55',
        bar: 'from-white/40 to-white/20',
        stroke: 'stroke-white/30',
        node: 'border-white/15 bg-white/[0.04] text-white/60',
    },
};

function pct(m) {
    if (m?.progress === null || m?.progress === undefined || m?.progress === '') return null;
    const n = Number(m.progress);
    if (!Number.isFinite(n)) return null;
    return Math.max(0, Math.min(100, Math.round(n)));
}

function statusKey(m) {
    if (m?.live) return 'active';
    const p = pct(m);
    if (p !== null && p >= 100) return 'done';
    if (p !== null && p > 0) return 'progress';
    return 'planned';
}

function statusOf(m) {
    return STATUS[statusKey(m)];
}

function iconPath(key) {
    return icons[key] ?? '';
}

// ── Tổng quan tiến độ lộ trình ──────────────────────────────────────────
const overall = computed(() => {
    const vals = milestones.value.map(pct).filter((v) => v !== null);
    if (vals.length) {
        return Math.round(vals.reduce((a, b) => a + b, 0) / vals.length);
    }
    const total = milestones.value.length;
    if (!total) return 0;
    const done = milestones.value.filter((m) => m.live).length;
    return Math.round((done / total) * 100);
});

// ── Mốc đang theo dõi (mặc định = mốc live, bấm để đổi) ─────────────────
const userPick = ref(null);
const defaultActive = computed(() => {
    const i = milestones.value.findIndex((m) => m.live);
    return i >= 0 ? i : 0;
});
const activeIndex = computed(() => userPick.value ?? defaultActive.value);
const activeMilestone = computed(() => milestones.value[activeIndex.value] ?? {});
const activePct = computed(() => pct(activeMilestone.value) ?? 0);

function select(i) {
    userPick.value = i;
}

// Vòng tiến độ trong bảng "người dẫn đường"
const RING_C = 2 * Math.PI * 30;
const ringOffset = computed(() => RING_C * (1 - activePct.value / 100));
</script>

<template>
  <section
    id="lo-trinh"
    ref="target"
    class="relative overflow-hidden scroll-mt-24 py-16 sm:scroll-mt-28 sm:py-20 md:py-24"
  >
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand/[0.08] via-[#05060c] to-brand/[0.06]" />
    <SectionParticleNetwork
      tone="violet"
      :seed="29"
    />
    <CongngheBrandBackdrop
      variant="dragon"
      align="left"
      opacity-class="opacity-[0.04]"
    />

    <div class="relative z-10 mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        :eyebrow="heading.eyebrow"
        :title="heading.title"
        :subtitle="heading.subtitle"
      />

      <div class="mt-10 grid gap-10 lg:mt-12 lg:grid-cols-2 lg:items-start lg:gap-12 xl:gap-16">
        <!-- Cột trái: tổng quan + timeline -->
        <div class="min-w-0">
          <!-- Thanh tiến độ tổng thể -->
          <div
            class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition-all duration-700 sm:p-5"
            :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0'"
          >
            <div class="flex items-end justify-between gap-3">
              <div>
                <p class="font-mono text-[11px] font-semibold uppercase tracking-wider text-brand-300">
                  Tiến độ tổng thể
                </p>
                <p class="mt-0.5 text-[12.5px] text-white/45">
                  {{ milestones.length }} chặng trên lộ trình
                </p>
              </div>
              <p class="font-display text-3xl font-bold leading-none text-white">
                {{ overall }}<span class="text-lg text-white/40">%</span>
              </p>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
              <div
                class="h-full rounded-full bg-gradient-to-r from-brand via-[#ff4d8d] to-brand/60"
                :style="{ width: shown ? `${overall}%` : '0%' }"
                style="transition: width 1100ms cubic-bezier(0.22, 1, 0.36, 1) 250ms"
              />
            </div>
          </div>

          <ol class="relative space-y-7 before:absolute before:left-[18px] before:top-3 before:h-[calc(100%-1.5rem)] before:w-px before:bg-white/10 sm:before:left-[22px]">
            <!-- Đường timeline "vẽ" dần theo scroll -->
            <span
              class="pointer-events-none absolute left-[18px] top-3 h-[calc(100%-1.5rem)] w-px origin-top bg-gradient-to-b from-brand via-[#ff4d8d] to-brand/40 sm:left-[22px]"
              :style="{ transform: `scaleY(${drawProgress})` }"
              aria-hidden="true"
            />
            <li
              v-for="(m, i) in milestones"
              :key="m.title"
              class="relative pl-14 transition-all duration-700 sm:pl-16"
              :class="shown ? 'translate-x-0 opacity-100' : (i % 2 ? 'translate-x-5 opacity-0' : '-translate-x-5 opacity-0')"
              :style="{ transitionDelay: `${i * 120}ms` }"
            >
              <!-- Nốt mốc: icon tuỳ biến / số, bấm để chọn -->
              <button
                type="button"
                class="absolute left-0 top-1 grid h-9 w-9 place-items-center overflow-hidden rounded-full border transition-all duration-300 hover:scale-110 sm:h-11 sm:w-11"
                :class="[statusOf(m).node, activeIndex === i ? 'scale-110 shadow-lg shadow-brand/20' : '']"
                :aria-label="`Xem mốc: ${m.title}`"
                :aria-current="activeIndex === i ? 'step' : undefined"
                @click="select(i)"
              >
                <svg
                  v-if="iconPath(m.icon)"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                ><path :d="iconPath(m.icon)" /></svg>
                <CongngheBrandImage
                  v-else-if="i === 0"
                  :src="congngheBrand.badgeCircle"
                  alt=""
                  class="h-[85%] w-[85%]"
                />
                <span
                  v-else
                  class="font-mono text-[12px] font-bold"
                >{{ i + 1 }}</span>
                <span
                  v-if="m.live"
                  class="absolute inset-0 rounded-full ring-2 animate-cn-ping-ring"
                  :class="statusOf(m).ring"
                />
              </button>

              <GlassCard
                tilt
                :padded="false"
                class="cursor-pointer p-6 transition-shadow"
                :class="activeIndex === i ? 'ring-1 ring-brand/40' : ''"
                @click="select(i)"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p
                    v-if="m.period"
                    class="font-mono text-[12px] font-semibold uppercase tracking-wider text-brand-300"
                  >
                    {{ m.period }}
                  </p>
                  <span
                    v-if="m.state"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium"
                    :class="statusOf(m).badge"
                  >
                    <span
                      v-if="m.live"
                      class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-cn-glow"
                    />
                    {{ m.state }}
                  </span>
                </div>
                <h3 class="mt-2 font-display text-lg font-bold text-white">
                  {{ m.title }}
                </h3>
                <p class="mt-1.5 text-sm leading-relaxed text-white/55">
                  {{ m.body }}
                </p>

                <!-- Thanh tiến độ từng mốc (hiện khi admin nhập %) -->
                <div
                  v-if="pct(m) !== null"
                  class="mt-4"
                >
                  <div class="flex items-center justify-between text-[11px] font-medium text-white/45">
                    <span>Tiến độ</span>
                    <span class="font-mono text-white/70">{{ pct(m) }}%</span>
                  </div>
                  <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10">
                    <div
                      class="h-full rounded-full bg-gradient-to-r"
                      :class="statusOf(m).bar"
                      :style="{ width: shown ? `${pct(m)}%` : '0%' }"
                      style="transition: width 900ms cubic-bezier(0.22, 1, 0.36, 1) 400ms"
                    />
                  </div>
                </div>
              </GlassCard>
            </li>
          </ol>
        </div>

        <!-- Cột phải: người dẫn đường (mascot + mốc đang theo dõi) -->
        <aside
          class="relative lg:sticky lg:top-24 lg:self-start"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
          style="transition: opacity 700ms, transform 700ms"
        >
          <div class="rounded-2xl border border-brand/20 bg-gradient-to-b from-brand/15 to-white/[0.03] p-5 backdrop-blur-sm lg:p-7">
            <p
              v-if="guideLabel"
              class="text-center font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-300 sm:text-[11px]"
            >
              {{ guideLabel }}
            </p>
            <CongngheBrandImage
              :src="congngheBrand.mascotVaJacket"
              alt="Linh vật VAS đồng hành lộ trình công nghệ"
              class="mx-auto mt-4 w-full max-h-[min(38vh,320px)] drop-shadow-[0_20px_48px_rgba(154,0,54,0.35)] lg:mt-5 lg:max-h-[360px]"
              loading="lazy"
            />

            <!-- Mốc đang theo dõi -->
            <div
              v-if="activeMilestone.title"
              class="mt-5 flex items-center gap-4 rounded-xl border border-white/10 bg-black/20 p-4"
            >
              <div class="relative h-[72px] w-[72px] shrink-0">
                <svg
                  viewBox="0 0 72 72"
                  class="h-full w-full -rotate-90"
                >
                  <circle
                    cx="36"
                    cy="36"
                    r="30"
                    fill="none"
                    stroke-width="6"
                    class="stroke-white/10"
                  />
                  <circle
                    cx="36"
                    cy="36"
                    r="30"
                    fill="none"
                    stroke-width="6"
                    stroke-linecap="round"
                    class="transition-all duration-700"
                    :class="statusOf(activeMilestone).stroke"
                    :stroke-dasharray="RING_C"
                    :stroke-dashoffset="ringOffset"
                  />
                </svg>
                <span class="absolute inset-0 grid place-items-center font-mono text-sm font-bold text-white">
                  {{ activePct }}%
                </span>
              </div>
              <div class="min-w-0">
                <p
                  v-if="activeMilestone.period"
                  class="font-mono text-[11px] font-semibold uppercase tracking-wider text-brand-300"
                >
                  {{ activeMilestone.period }}
                </p>
                <p class="mt-0.5 line-clamp-2 font-display text-sm font-bold text-white">
                  {{ activeMilestone.title }}
                </p>
                <span
                  v-if="activeMilestone.state"
                  class="mt-1.5 inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10.5px] font-medium"
                  :class="statusOf(activeMilestone).badge"
                >
                  {{ activeMilestone.state }}
                </span>
              </div>
            </div>

            <p
              v-if="companionNote"
              class="mt-4 text-center text-[12.5px] leading-relaxed text-white/45"
            >
              {{ companionNote }}
            </p>
          </div>
        </aside>
      </div>
    </div>
  </section>
</template>
