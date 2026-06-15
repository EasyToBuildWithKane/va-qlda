<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import SectionHeading from './SectionHeading.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { useInView, prefersReducedMotionNow } from './motion.js';
import { openCongngheProject } from './useCongngheProjectModal.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.1 });

const heading = computed(() => props.content?.heading ?? {});

const trackRef = ref(null);
const activeIndex = ref(0);
let scrollRaf = null;

const slideCount = computed(() => props.products.length);
const isSingleProduct = computed(() => slideCount.value === 1);
const canPrev = computed(() => activeIndex.value > 0);
const canNext = computed(() => activeIndex.value < slideCount.value - 1);

watch(
    () => props.products.length,
    (len) => {
        if (activeIndex.value >= len) {
            activeIndex.value = Math.max(0, len - 1);
        }
    },
);

function syncActiveFromScroll() {
    const track = trackRef.value;
    if (!track || !slideCount.value) {
        return;
    }
    const cards = track.querySelectorAll('[data-eco-card]');
    if (!cards.length) {
        return;
    }
    const center = track.scrollLeft + track.clientWidth / 2;
    let best = 0;
    let bestDist = Infinity;
    cards.forEach((node, i) => {
        const el = /** @type {HTMLElement} */ (node);
        const mid = el.offsetLeft + el.offsetWidth / 2;
        const dist = Math.abs(mid - center);
        if (dist < bestDist) {
            bestDist = dist;
            best = i;
        }
    });
    activeIndex.value = best;
}

function onTrackScroll() {
    if (scrollRaf) {
        return;
    }
    scrollRaf = requestAnimationFrame(() => {
        scrollRaf = null;
        syncActiveFromScroll();
    });
}

function scrollToIndex(i) {
    const track = trackRef.value;
    if (!track || i < 0 || i >= slideCount.value) {
        return;
    }
    const card = track.querySelector(`[data-eco-card="${i}"]`);
    if (!card) {
        return;
    }
    const el = /** @type {HTMLElement} */ (card);
    const left = el.offsetLeft - (track.clientWidth - el.offsetWidth) / 2;
    track.scrollTo({
        left: Math.max(0, left),
        behavior: prefersReducedMotionNow() ? 'auto' : 'smooth',
    });
    activeIndex.value = i;
}

function goStep(delta) {
    scrollToIndex(activeIndex.value + delta);
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
        goStep(-1);
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        goStep(1);
    }
}

function coverUrl(product) {
    return product?.images?.[0]?.url ?? null;
}

function openProduct(product) {
    openCongngheProject(product);
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    nextTick(() => {
        if (slideCount.value) {
            scrollToIndex(activeIndex.value);
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    if (scrollRaf) {
        cancelAnimationFrame(scrollRaf);
    }
});
</script>

<template>
  <section
    id="san-pham"
    ref="target"
    class="relative py-20"
    tabindex="-1"
  >
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 -z-0"
    >
      <div class="absolute left-[4%] top-16 h-56 w-56 rounded-full bg-emerald-500/12 blur-[100px] animate-cn-float" />
      <div class="absolute right-[6%] bottom-6 h-64 w-64 rounded-full bg-cyan-500/10 blur-[100px] animate-cn-float-x" />
    </div>

    <div class="relative mx-auto min-w-0 max-w-7xl px-5 sm:px-8">
      <div
        class="flex min-w-0 flex-wrap items-end justify-between gap-4 transition-all duration-700"
        :class="sectionVisible ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0'"
      >
        <SectionHeading
          class="min-w-0 max-w-2xl flex-1"
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />
        <div
          v-if="slideCount"
          class="flex shrink-0 items-center gap-2"
        >
          <span class="hidden rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 font-mono text-[10px] uppercase tracking-wider text-emerald-200/90 sm:inline">
            {{ slideCount }} sản phẩm
          </span>
          <button
            type="button"
            class="grid h-9 w-9 place-items-center rounded-full border border-emerald-400/25 bg-white/5 text-white/70 transition enabled:hover:border-emerald-400/50 enabled:hover:bg-emerald-400/10 enabled:hover:text-white disabled:opacity-30"
            :disabled="!canPrev"
            aria-label="Sản phẩm trước"
            @click="goStep(-1)"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M15 6l-6 6 6 6" /></svg>
          </button>
          <span class="min-w-[3.25rem] text-center font-mono text-[11px] tabular-nums text-white/45">
            {{ activeIndex + 1 }}/{{ slideCount }}
          </span>
          <button
            type="button"
            class="grid h-9 w-9 place-items-center rounded-full border border-emerald-400/25 bg-white/5 text-white/70 transition enabled:hover:border-emerald-400/50 enabled:hover:bg-emerald-400/10 enabled:hover:text-white disabled:opacity-30"
            :disabled="!canNext"
            aria-label="Sản phẩm sau"
            @click="goStep(1)"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M9 6l6 6-6 6" /></svg>
          </button>
        </div>
      </div>

      <div
        v-if="slideCount"
        class="cn-eco-carousel relative mt-8 min-w-0 transition-all duration-700 delay-100"
        :class="[
          sectionVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
          isSingleProduct ? 'cn-eco-carousel--single' : '',
        ]"
      >
        <div
          ref="trackRef"
          class="cn-eco-track flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain scroll-smooth py-4 sm:gap-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          role="list"
          aria-label="Danh sách sản phẩm hoàn thành"
          @scroll="onTrackScroll"
        >
          <article
            v-for="(product, idx) in products"
            :key="product.id"
            role="listitem"
            :data-eco-card="idx"
            class="cn-eco-card group relative w-[min(88vw,22.5rem)] shrink-0 snap-center sm:w-[22.5rem] lg:w-[24rem]"
            :class="[
              sectionVisible ? 'cn-eco-card--in' : 'opacity-0',
              activeIndex === idx ? 'cn-eco-card--active' : 'cn-eco-card--idle',
            ]"
            :style="{ '--eco-delay': `${idx * 70}ms`, '--eco-hue': product.color || '#34d399' }"
          >
            <span
              class="cn-eco-card__halo pointer-events-none absolute -inset-1 rounded-[1.35rem] opacity-0 transition-opacity duration-500 group-hover:opacity-100"
              aria-hidden="true"
            />
            <button
              type="button"
              class="cn-eco-card__btn relative flex min-h-[22.5rem] w-full flex-col overflow-hidden rounded-3xl border border-white/10 bg-[#0a0c14]/92 text-left shadow-[0_16px_48px_-24px_rgba(0,0,0,0.85)] ring-1 ring-white/5 backdrop-blur-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400/60 sm:min-h-[23.5rem]"
              @click="openProduct(product)"
            >
              <span
                class="cn-eco-card__border pointer-events-none absolute inset-0 rounded-3xl"
                aria-hidden="true"
              />
              <span
                class="cn-eco-card__mesh pointer-events-none absolute inset-0 rounded-3xl opacity-[0.35]"
                aria-hidden="true"
              />
              <!-- Ảnh bìa -->
              <div class="relative aspect-[16/9] shrink-0 overflow-hidden bg-white/[0.04]">
                <img
                  v-if="coverUrl(product)"
                  :src="coverUrl(product)"
                  alt=""
                  class="cn-eco-card__cover h-full w-full object-cover"
                  loading="lazy"
                  decoding="async"
                >
                <div
                  v-else
                  class="flex h-full w-full items-center justify-center"
                  :style="{ background: `linear-gradient(135deg, ${product.color || '#9A0036'}66, #0a0c14 70%)` }"
                >
                  <span class="font-display text-4xl font-bold text-white/25">{{ (product.code || product.name || '?').slice(0, 2) }}</span>
                </div>
                <span
                  class="cn-eco-card__scan pointer-events-none absolute inset-x-0 top-0 h-[40%] opacity-0"
                  aria-hidden="true"
                />
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#0a0c14] via-[#0a0c14]/20 to-transparent opacity-95" />
                <span class="pointer-events-none absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-emerald-400/25 bg-black/40 px-2 py-0.5 font-mono text-[9px] uppercase tracking-wider text-emerald-200/80 backdrop-blur-md">
                  <span class="h-1 w-1 rounded-full bg-emerald-400 animate-cn-glow" />
                  Live
                </span>
                <span
                  v-if="product.images?.length > 1"
                  class="absolute bottom-2.5 right-2.5 rounded-md bg-black/50 px-2 py-0.5 font-mono text-[9px] text-white/70 backdrop-blur-sm"
                >
                  +{{ product.images.length - 1 }} ảnh
                </span>
              </div>

              <!-- Nội dung -->
              <div class="relative flex flex-1 flex-col p-4 sm:p-5">
                <p
                  v-if="product.code"
                  class="font-mono text-[10px] uppercase tracking-[0.18em] text-emerald-200/55"
                >
                  {{ product.code }}
                </p>
                <h3 class="mt-1.5 line-clamp-2 font-display text-[17px] font-bold leading-snug text-white sm:text-lg">
                  {{ product.name }}
                </h3>
                <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-white/50 sm:text-[13px]">
                  {{ product.description || 'Nền tảng đã nghiệm thu và vận hành.' }}
                </p>

                <div class="mt-auto flex items-center justify-between gap-2 border-t border-white/[0.08] pt-4">
                  <div
                    v-if="product.manager"
                    class="flex min-w-0 items-center gap-2.5"
                  >
                    <Avatar
                      :name="product.manager.name"
                      :src="product.manager.avatar"
                      :size="32"
                    />
                    <span class="truncate text-xs text-white/55">{{ product.manager.name }}</span>
                  </div>
                  <span
                    v-else
                    class="text-xs text-white/35"
                  >Chưa gán PM</span>
                  <span class="cn-eco-card__cta inline-flex shrink-0 items-center gap-0.5 text-xs font-semibold text-emerald-300/90">
                    Chi tiết
                    <svg
                      width="15"
                      height="15"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                    ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                  </span>
                </div>
              </div>
            </button>
          </article>
        </div>

        <!-- Chấm điều hướng (nhiều sản phẩm) -->
        <div
          v-if="slideCount > 1"
          class="mt-4 flex flex-wrap items-center justify-center gap-1.5"
        >
          <button
            v-for="(_, dotIdx) in products"
            :key="`eco-dot-${dotIdx}`"
            type="button"
            class="h-1.5 rounded-full transition-all duration-300"
            :class="dotIdx === activeIndex
              ? 'w-6 bg-gradient-to-r from-emerald-400 to-cyan-400'
              : 'w-1.5 bg-white/25 hover:bg-white/45'"
            :aria-label="`Đến sản phẩm ${dotIdx + 1}`"
            :aria-current="dotIdx === activeIndex ? 'true' : undefined"
            @click="scrollToIndex(dotIdx)"
          />
        </div>
      </div>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-emerald-400/20 bg-emerald-400/[0.04] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có dự án nào hoàn thành. Dữ liệu sẽ hiển thị khi có sản phẩm được nghiệm thu.
      </p>
    </div>
  </section>
</template>

<style scoped>
/* Full-bleed trong khung max-w-7xl; padding để snap-center không cắt mép thẻ */
.cn-eco-carousel {
    margin-inline: -1.25rem;
    width: calc(100% + 2.5rem);
}

@media (min-width: 640px) {
    .cn-eco-carousel {
        margin-inline: -2rem;
        width: calc(100% + 4rem);
    }
}

.cn-eco-carousel--single {
    margin-inline: 0;
    width: 100%;
}

.cn-eco-carousel--single .cn-eco-track {
    justify-content: center;
    overflow-x: hidden;
    --eco-edge: 0px;
    padding-inline: 0;
    scroll-padding-inline: 0;
}

.cn-eco-track {
    --eco-card-w: min(88vw, 22.5rem);
    --eco-edge: max(1.25rem, calc((100% - var(--eco-card-w)) / 2));
    padding-inline: var(--eco-edge);
    scroll-padding-inline: var(--eco-edge);
    perspective: 1200px;
}

@media (min-width: 1024px) {
    .cn-eco-track {
        --eco-card-w: 24rem;
    }
}

.cn-eco-card {
    transform-style: preserve-3d;
    transition: transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.cn-eco-card--in:not(.cn-eco-card--active) {
    animation: cn-eco-card-in 0.75s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--eco-delay, 0ms);
}

.cn-eco-card--in.cn-eco-card--active {
    animation:
        cn-eco-card-in 0.75s cubic-bezier(0.16, 1, 0.3, 1) both,
        cn-eco-card-float 6s ease-in-out infinite;
    animation-delay: var(--eco-delay, 0ms), calc(var(--eco-delay, 0ms) + 750ms);
}

.cn-eco-card--idle {
    opacity: 0.68;
    filter: saturate(0.85);
}

.cn-eco-card--active {
    opacity: 1;
    filter: none;
    z-index: 2;
}

.cn-eco-card--active .cn-eco-card__halo {
    opacity: 0.85;
}

.cn-eco-card__halo {
    background: radial-gradient(
        ellipse 80% 60% at 50% 40%,
        color-mix(in srgb, var(--eco-hue, #34d399) 35%, transparent),
        transparent 70%
    );
    filter: blur(12px);
}

.cn-eco-card__btn {
    transition:
        transform 0.45s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.35s,
        box-shadow 0.45s;
}

.group:hover .cn-eco-card__btn,
.cn-eco-card--active .cn-eco-card__btn {
    border-color: rgba(52, 211, 153, 0.4);
    box-shadow:
        0 0 0 1px rgba(52, 211, 153, 0.12),
        0 24px 56px -20px rgba(52, 211, 153, 0.28),
        0 16px 48px -24px rgba(0, 0, 0, 0.85);
}

.group:hover .cn-eco-card__btn {
    transform: translateY(-6px) rotateX(2deg);
}

.cn-eco-card--active .cn-eco-card__btn {
    transform: translateY(-4px);
}

.cn-eco-card__border {
    padding: 1px;
    background: conic-gradient(
        from var(--eco-border-angle, 0deg),
        rgba(52, 211, 153, 0.7),
        rgba(6, 182, 212, 0.5),
        rgba(124, 58, 237, 0.45),
        rgba(52, 211, 153, 0.7)
    );
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0.35;
    animation: cn-eco-border-spin 8s linear infinite;
}

.group:hover .cn-eco-card__border,
.cn-eco-card--active .cn-eco-card__border {
    opacity: 0.85;
}

.cn-eco-card__mesh {
    background-image:
        linear-gradient(rgba(52, 211, 153, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(52, 211, 153, 0.06) 1px, transparent 1px);
    background-size: 24px 24px;
    animation: cn-eco-grid-pan 10s linear infinite;
}

.cn-eco-card__cover {
    transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1), filter 0.5s;
}

.group:hover .cn-eco-card__cover {
    transform: scale(1.06);
    filter: saturate(1.08) brightness(1.05);
}

.cn-eco-card__scan {
    background: linear-gradient(
        180deg,
        transparent 0%,
        rgba(52, 211, 153, 0.12) 45%,
        rgba(255, 255, 255, 0.08) 50%,
        rgba(6, 182, 212, 0.1) 55%,
        transparent 100%
    );
}

.group:hover .cn-eco-card__scan {
    opacity: 1;
    animation: cn-eco-scan 2.2s ease-in-out infinite;
}

.cn-eco-card__cta {
    transition: gap 0.3s, transform 0.3s;
}

.group:hover .cn-eco-card__cta {
    gap: 0.35rem;
    transform: translateX(2px);
}

@property --eco-border-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

@keyframes cn-eco-border-spin {
    to {
        --eco-border-angle: 360deg;
    }
}

@keyframes cn-eco-grid-pan {
    0% {
        background-position: 0 0;
    }
    100% {
        background-position: 24px 24px;
    }
}

@keyframes cn-eco-scan {
    0% {
        transform: translateY(-120%);
    }
    100% {
        transform: translateY(220%);
    }
}

@keyframes cn-eco-card-in {
    from {
        opacity: 0;
        transform: translateY(28px) scale(0.9) rotateX(10deg);
        filter: blur(6px);
    }
    60% {
        filter: blur(0);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0);
        filter: blur(0);
    }
}

@keyframes cn-eco-card-float {
    0%,
    100% {
        translate: 0 0;
    }
    50% {
        translate: 0 -8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-eco-card--in,
    .cn-eco-card--active,
    .cn-eco-card__border,
    .cn-eco-card__mesh,
    .cn-eco-card__scan {
        animation: none;
    }

    .cn-eco-card--in {
        opacity: 1;
        filter: none;
    }

    .cn-eco-card--idle {
        opacity: 1;
        filter: none;
    }

    .group:hover .cn-eco-card__btn,
    .cn-eco-card--active .cn-eco-card__btn {
        transform: none;
    }

    .cn-eco-track {
        scroll-behavior: auto;
        perspective: none;
    }
}
</style>
