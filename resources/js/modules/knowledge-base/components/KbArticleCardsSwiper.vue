<script setup>
import { ref } from 'vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { A11y, Pagination } from 'swiper/modules';
import AppIcon from '@/Components/AppIcon.vue';
import 'swiper/css';
import 'swiper/css/pagination';

defineProps({
    articles: { type: Array, default: () => [] },
    ariaLabel: { type: String, default: 'Danh sách bài viết' },
});

const modules = [Pagination, A11y];

const swiperRef = ref(null);
const atStart = ref(true);
const atEnd = ref(false);

function syncNavState(swiper) {
    const s = swiper ?? swiperRef.value;
    if (!s) {
        return;
    }
    atStart.value = s.isBeginning;
    atEnd.value = s.isEnd;
}

function onSwiper(swiper) {
    swiperRef.value = swiper;
    syncNavState(swiper);
}

function goPrev() {
    swiperRef.value?.slidePrev();
}

function goNext() {
    swiperRef.value?.slideNext();
}

const breakpoints = {
    0: {
        slidesPerView: 1.12,
        spaceBetween: 12,
    },
    640: {
        slidesPerView: 2.15,
        spaceBetween: 14,
    },
    1024: {
        slidesPerView: 3.12,
        spaceBetween: 16,
    },
    1280: {
        slidesPerView: 3.35,
        spaceBetween: 16,
    },
    1536: {
        slidesPerView: 4.12,
        spaceBetween: 18,
    },
};
</script>

<template>
  <div
    v-if="articles.length"
    class="kb-articles-swiper relative min-w-0"
  >
    <div class="mb-2 flex items-center justify-end gap-1.5 sm:absolute sm:right-0 sm:top-0 sm:z-10 sm:-translate-y-[calc(100%+0.75rem)]">
      <button
        type="button"
        class="kb-articles-swiper__nav kb-articles-swiper__nav--prev"
        aria-label="Bài trước"
        :disabled="atStart"
        @click="goPrev"
      >
        <AppIcon
          name="chevron-left"
          :size="18"
        />
      </button>
      <button
        type="button"
        class="kb-articles-swiper__nav kb-articles-swiper__nav--next"
        aria-label="Bài tiếp theo"
        :disabled="atEnd"
        @click="goNext"
      >
        <AppIcon
          name="chevron-right"
          :size="18"
        />
      </button>
    </div>

    <Swiper
      :modules="modules"
      :slides-per-view="1.12"
      :space-between="12"
      :breakpoints="breakpoints"
      :pagination="{ clickable: true, dynamicBullets: true }"
      :grab-cursor="true"
      :watch-overflow="true"
      class="kb-articles-swiper__root !overflow-visible pb-8"
      :aria-label="ariaLabel"
      @swiper="onSwiper"
      @slide-change="syncNavState"
      @reach-beginning="syncNavState"
      @reach-end="syncNavState"
      @from-edge="syncNavState"
      @resize="syncNavState"
    >
      <SwiperSlide
        v-for="article in articles"
        :key="article.id"
        class="!h-auto"
      >
        <slot
          name="slide"
          :article="article"
        />
      </SwiperSlide>
    </Swiper>
  </div>
</template>

<style scoped>
.kb-articles-swiper__nav {
    @apply grid h-9 w-9 shrink-0 place-items-center rounded-full border border-slate-200/90 bg-white text-slate-600 shadow-sm transition hover:border-brand/30 hover:text-brand disabled:pointer-events-none disabled:opacity-35 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-brand/40;
}

.kb-articles-swiper__root :deep(.swiper-pagination) {
    @apply bottom-0;
}

.kb-articles-swiper__root :deep(.swiper-pagination-bullet) {
    @apply h-1.5 w-1.5 bg-slate-300 opacity-100 transition dark:bg-slate-600;
}

.kb-articles-swiper__root :deep(.swiper-pagination-bullet-active) {
    @apply w-5 rounded-full bg-brand;
}
</style>
