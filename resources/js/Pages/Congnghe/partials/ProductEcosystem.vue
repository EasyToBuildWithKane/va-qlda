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
          class="cn-eco-track flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain scroll-smooth py-2 sm:gap-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          role="list"
          aria-label="Danh sách sản phẩm hoàn thành"
          @scroll="onTrackScroll"
        >
          <article
            v-for="(product, idx) in products"
            :key="product.id"
            role="listitem"
            :data-eco-card="idx"
            class="cn-eco-card group relative w-[min(78vw,17.5rem)] shrink-0 snap-center sm:w-[17.5rem]"
            :class="[
              sectionVisible ? 'cn-eco-card--in' : 'opacity-0',
              activeIndex === idx ? 'cn-eco-card--active' : '',
            ]"
            :style="{ '--eco-delay': `${idx * 55}ms` }"
          >
            <button
              type="button"
              class="flex h-full w-full flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0a0c14]/90 text-left shadow-[0_12px_40px_-20px_rgba(0,0,0,0.8)] ring-1 ring-white/5 transition duration-300 hover:border-emerald-400/35 hover:shadow-[0_16px_48px_-16px_rgba(52,211,153,0.25)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400/60"
              @click="openProduct(product)"
            >
              <!-- Ảnh bìa -->
              <div class="relative aspect-[16/10] overflow-hidden bg-white/[0.04]">
                <img
                  v-if="coverUrl(product)"
                  :src="coverUrl(product)"
                  alt=""
                  class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                  loading="lazy"
                  decoding="async"
                >
                <div
                  v-else
                  class="flex h-full w-full items-center justify-center"
                  :style="{ background: `linear-gradient(135deg, ${product.color || '#9A0036'}55, #0a0c14)` }"
                >
                  <span class="font-display text-3xl font-bold text-white/20">{{ (product.code || product.name || '?').slice(0, 2) }}</span>
                </div>
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#0a0c14] via-transparent to-transparent opacity-90" />
                <span
                  v-if="product.images?.length > 1"
                  class="absolute bottom-2 right-2 rounded-md bg-black/50 px-1.5 py-0.5 font-mono text-[9px] text-white/70 backdrop-blur-sm"
                >
                  +{{ product.images.length - 1 }} ảnh
                </span>
              </div>

              <!-- Nội dung gọn -->
              <div class="flex flex-1 flex-col p-3.5 sm:p-4">
                <p
                  v-if="product.code"
                  class="font-mono text-[10px] uppercase tracking-[0.16em] text-emerald-200/50"
                >
                  {{ product.code }}
                </p>
                <h3 class="mt-1 line-clamp-2 font-display text-[15px] font-bold leading-snug text-white">
                  {{ product.name }}
                </h3>
                <p class="mt-1.5 line-clamp-2 text-[11px] leading-relaxed text-white/50">
                  {{ product.description || 'Nền tảng đã nghiệm thu và vận hành.' }}
                </p>

                <div class="mt-auto flex items-center justify-between gap-2 border-t border-white/[0.06] pt-3">
                  <div
                    v-if="product.manager"
                    class="flex min-w-0 items-center gap-2"
                  >
                    <Avatar
                      :name="product.manager.name"
                      :src="product.manager.avatar"
                      :size="28"
                    />
                    <span class="truncate text-[11px] text-white/55">{{ product.manager.name }}</span>
                  </div>
                  <span
                    v-else
                    class="text-[11px] text-white/35"
                  >Chưa gán PM</span>
                  <span class="inline-flex shrink-0 items-center gap-0.5 text-[11px] font-semibold text-emerald-300/90 transition group-hover:gap-1">
                    Chi tiết
                    <svg
                      width="14"
                      height="14"
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
    --eco-card-w: min(78vw, 17.5rem);
    --eco-edge: max(1.25rem, calc((100% - var(--eco-card-w)) / 2));
    padding-inline: var(--eco-edge);
    scroll-padding-inline: var(--eco-edge);
}

.cn-eco-card--in {
    animation: cn-eco-card-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: var(--eco-delay, 0ms);
}

.cn-eco-card--active button {
    border-color: rgba(52, 211, 153, 0.35);
    box-shadow: 0 0 0 1px rgba(52, 211, 153, 0.15), 0 16px 48px -16px rgba(52, 211, 153, 0.2);
}

@keyframes cn-eco-card-in {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-eco-card--in {
        animation: none;
        opacity: 1;
    }

    .cn-eco-track {
        scroll-behavior: auto;
    }
}
</style>
