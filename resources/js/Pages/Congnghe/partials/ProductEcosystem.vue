<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { tone } from './tones.js';
import { useInView } from './motion.js';
import { openCongngheProject } from './useCongngheProjectModal.js';

const props = defineProps({
    products: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.12 });

const slideIndex = ref(0);
const slideDirection = ref(1);

const slideCount = computed(() => props.products.length);
const current = computed(() => props.products[slideIndex.value] ?? null);

const canPrev = computed(() => slideIndex.value > 0);
const canNext = computed(() => slideIndex.value < slideCount.value - 1);

watch(
    () => props.products.length,
    (len) => {
        if (slideIndex.value >= len) {
            slideIndex.value = Math.max(0, len - 1);
        }
    },
);

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
    id="san-pham"
    ref="target"
    class="relative overflow-hidden py-20"
    tabindex="-1"
  >
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 -z-0"
    >
      <div class="absolute left-[6%] top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-[120px] animate-cn-float" />
      <div class="absolute right-[8%] bottom-8 h-72 w-72 rounded-full bg-brand/12 blur-[120px] animate-cn-float-x" />
    </div>

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-wrap items-end justify-between gap-6">
        <SectionHeading
          eyebrow="Hệ sinh thái sản phẩm"
          title="Những nền tảng đã hoàn thành"
          subtitle="Sản phẩm đã nghiệm thu — lướt slide để xem từng nền tảng, mô tả và hình ảnh tham chiếu."
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

      <div
        v-if="slideCount && current"
        class="mt-10"
      >
        <div
          class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-400/15 bg-emerald-400/[0.06] px-4 py-3 backdrop-blur-sm"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-200">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-cn-glow" />
              Đã nghiệm thu · vận hành
            </span>
            <span
              v-if="current.type_label"
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
              :class="tone(current.type_color).soft"
            >
              {{ current.type_label }}
            </span>
          </div>
          <p class="font-mono text-[10px] uppercase tracking-wider text-white/35">
            Phím ← → để lướt
          </p>
        </div>

        <div class="relative overflow-hidden rounded-3xl ring-1 ring-white/10">
          <div
            class="flex transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
            :style="{ transform: `translateX(-${slideIndex * 100}%)` }"
          >
            <article
              v-for="(product, idx) in products"
              :key="product.id"
              class="w-full shrink-0 px-0.5"
              :aria-hidden="idx !== slideIndex"
            >
              <GlassCard
                tilt
                :padded="false"
                class="cn-product-slide min-h-[24rem] overflow-hidden p-0 sm:min-h-[22rem]"
                :class="idx === slideIndex ? (slideDirection > 0 ? 'cn-product-slide--in-next' : 'cn-product-slide--in-prev') : ''"
              >
                <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
                  <!-- Nội dung -->
                  <div
                    class="flex flex-col justify-between border-b border-white/10 p-6 sm:p-8 lg:border-b-0 lg:border-r"
                    :style="{
                      background: `linear-gradient(160deg, ${product.color || '#9A0036'}18 0%, transparent 50%), rgba(255,255,255,0.02)`,
                    }"
                  >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                      <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset"
                        :class="tone(product.statusColor).soft"
                      >
                        <span
                          class="h-1.5 w-1.5 rounded-full"
                          :class="tone(product.statusColor).dot"
                        />
                        {{ product.status }}
                      </span>
                      <span
                        v-if="product.images?.length"
                        class="font-mono text-[10px] uppercase tracking-wider text-white/40"
                      >
                        {{ product.images.length }} ảnh tham chiếu
                      </span>
                    </div>

                    <div class="mt-6 lg:mt-4">
                      <p
                        v-if="product.code"
                        class="font-mono text-[11px] uppercase tracking-[0.2em] text-white/40"
                      >
                        {{ product.code }}
                      </p>
                      <h3 class="mt-2 font-display text-2xl font-bold leading-tight text-white sm:text-[1.75rem] lg:text-3xl">
                        {{ product.name }}
                      </h3>
                      <p class="mt-4 text-sm leading-relaxed text-white/60 sm:text-[15px] lg:line-clamp-4">
                        {{ product.description || 'Nền tảng đã hoàn thành và đưa vào vận hành.' }}
                      </p>
                    </div>

                    <div class="mt-6 space-y-5">
                      <div>
                        <div class="flex items-center justify-between font-mono text-[11px] text-white/55">
                          <span>TIẾN ĐỘ TỔNG THỂ</span>
                          <span class="text-base font-semibold text-white">{{ product.progress }}%</span>
                        </div>
                        <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-white/10">
                          <div
                            class="h-full rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d,#9A0036)] bg-[length:200%_100%] animate-cn-shimmer"
                            :style="{ width: `${product.progress}%` }"
                          />
                        </div>
                      </div>

                      <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand to-[#c4185b] px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition hover:brightness-110 sm:w-auto"
                        @click="openCongngheProject(product)"
                      >
                        Xem chi tiết &amp; gallery
                        <svg
                          width="16"
                          height="16"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                      </button>
                    </div>
                  </div>

                  <!-- Preview & phụ trách -->
                  <div class="flex flex-col p-6 sm:p-8">
                    <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40">
                      Hình ảnh tham chiếu
                    </p>
                    <button
                      type="button"
                      class="group relative mt-3 flex-1 overflow-hidden rounded-2xl border border-white/10 bg-black/20 text-left transition hover:border-brand/35"
                      @click="openCongngheProject(product)"
                    >
                      <img
                        v-if="product.images?.[0]?.url"
                        :src="product.images[0].url"
                        :alt="product.images[0].caption || product.name"
                        class="h-full min-h-[11rem] w-full object-cover transition duration-500 group-hover:scale-[1.02] sm:min-h-[13rem]"
                      >
                      <div
                        v-else
                        class="flex min-h-[11rem] flex-col items-center justify-center gap-3 px-6 text-center sm:min-h-[13rem]"
                        :style="{
                          background: `linear-gradient(145deg, ${product.color || '#9A0036'}33, rgba(255,255,255,0.03))`,
                        }"
                      >
                        <span class="grid h-14 w-14 place-items-center rounded-2xl border border-white/15 bg-white/5 text-white/40">
                          <svg
                            width="28"
                            height="28"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                          ><rect
                            x="3"
                            y="3"
                            width="18"
                            height="18"
                            rx="2"
                          /><circle
                            cx="8.5"
                            cy="8.5"
                            r="1.5"
                          /><path d="m21 15-5-5L5 21" /></svg>
                        </span>
                        <p class="text-sm text-white/45">
                          Chưa có ảnh — bấm để xem mô tả đầy đủ
                        </p>
                      </div>
                      <span
                        class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#05060c]/90 via-[#05060c]/50 to-transparent px-4 py-3 text-xs font-medium text-white/80 opacity-0 transition group-hover:opacity-100"
                      >
                        Mở gallery dự án
                      </span>
                    </button>

                    <div
                      v-if="product.images?.length > 1"
                      class="mt-3 flex gap-2 overflow-x-auto pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                      <button
                        v-for="img in product.images.slice(0, 5)"
                        :key="img.id"
                        type="button"
                        class="h-12 w-16 shrink-0 overflow-hidden rounded-lg border border-white/15 opacity-80 transition hover:border-brand/40 hover:opacity-100"
                        @click="openCongngheProject(product)"
                      >
                        <img
                          :src="img.url"
                          :alt="img.caption"
                          class="h-full w-full object-cover"
                        >
                      </button>
                      <span
                        v-if="product.images.length > 5"
                        class="flex h-12 shrink-0 items-center px-2 font-mono text-[10px] text-white/40"
                      >
                        +{{ product.images.length - 5 }}
                      </span>
                    </div>

                    <div class="mt-6 border-t border-white/10 pt-5">
                      <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40">
                        Phụ trách chính
                      </p>
                      <div
                        v-if="product.manager"
                        class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-3.5"
                      >
                        <Avatar
                          :name="product.manager.name"
                          :src="product.manager.avatar"
                          :size="44"
                        />
                        <div class="min-w-0 flex-1">
                          <p class="truncate font-semibold text-white">
                            {{ product.manager.name }}
                          </p>
                          <p class="truncate text-sm text-white/50">
                            {{ product.manager.role_title || 'Phụ trách chính' }}
                          </p>
                        </div>
                      </div>
                      <p
                        v-else
                        class="mt-3 text-sm text-white/40"
                      >
                        Chưa phân công phụ trách
                      </p>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-1.5">
                      <button
                        v-for="(_, dotIdx) in products"
                        :key="`dot-${dotIdx}`"
                        type="button"
                        class="h-2 rounded-full transition-all duration-300"
                        :class="dotIdx === slideIndex
                          ? 'w-8 bg-[linear-gradient(110deg,#9A0036,#ff4d8d)]'
                          : 'w-2 bg-white/20 hover:bg-white/40'"
                        :aria-label="`Đến sản phẩm ${dotIdx + 1}`"
                        :aria-current="dotIdx === slideIndex ? 'true' : undefined"
                        @click="goToSlide(dotIdx)"
                      />
                    </div>
                  </div>
                </div>
              </GlassCard>
            </article>
          </div>
        </div>

        <!-- Thanh chọn nhanh -->
        <div
          v-if="slideCount > 1"
          class="mt-5 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          <button
            v-for="(product, idx) in products"
            :key="`thumb-${product.id}`"
            type="button"
            class="flex min-w-[10.5rem] max-w-[14rem] shrink-0 items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-all duration-200"
            :class="idx === slideIndex
              ? 'border-brand/50 bg-brand/10 shadow-[0_0_24px_-8px_rgba(154,0,54,0.8)]'
              : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.06]'"
            @click="goToSlide(idx)"
          >
            <span
              v-if="product.images?.[0]?.url"
              class="h-10 w-14 shrink-0 overflow-hidden rounded-lg ring-1 ring-white/10"
            >
              <img
                :src="product.images[0].url"
                alt=""
                class="h-full w-full object-cover"
              >
            </span>
            <span
              v-else
              class="h-10 w-10 shrink-0 rounded-lg ring-1 ring-inset ring-white/15"
              :style="{ backgroundColor: `${product.color || '#9A0036'}44` }"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-[12px] font-semibold text-white">{{ product.name }}</span>
              <span class="font-mono text-[10px] text-white/40">{{ product.code || '—' }}</span>
            </span>
          </button>
        </div>
      </div>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có dự án nào hoàn thành. Dữ liệu sẽ hiển thị khi có sản phẩm được nghiệm thu.
      </p>
    </div>
  </section>
</template>

<style scoped>
.cn-product-slide--in-next {
    animation: cn-product-in-next 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.cn-product-slide--in-prev {
    animation: cn-product-in-prev 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes cn-product-in-next {
    from {
        opacity: 0.65;
        transform: translateX(10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes cn-product-in-prev {
    from {
        opacity: 0.65;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-product-slide--in-next,
    .cn-product-slide--in-prev {
        animation: none;
    }
}
</style>
