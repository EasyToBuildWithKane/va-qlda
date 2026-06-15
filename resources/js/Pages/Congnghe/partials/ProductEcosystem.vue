<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SectionHeading from './SectionHeading.vue';
import { tone } from './tones.js';

defineProps({
    products: { type: Array, default: () => [] },
});

const scroller = ref(null);

function scrollBy(dir) {
    scroller.value?.scrollBy({ left: dir * 340, behavior: 'smooth' });
}
</script>

<template>
  <section
    id="san-pham"
    class="relative border-t border-white/5 py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-wrap items-end justify-between gap-6">
        <SectionHeading
          eyebrow="Hệ sinh thái sản phẩm"
          title="Những nền tảng đang vận hành"
          subtitle="Các sản phẩm phần mềm do Phòng Công Nghệ phát triển và duy trì, phục vụ trực tiếp công việc hằng ngày."
        />
        <div
          v-if="products.length"
          class="hidden gap-2 sm:flex"
        >
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 text-white/70 transition hover:bg-white/5 hover:text-white"
            aria-label="Trước"
            @click="scrollBy(-1)"
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
          <button
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 text-white/70 transition hover:bg-white/5 hover:text-white"
            aria-label="Sau"
            @click="scrollBy(1)"
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
        v-if="products.length"
        ref="scroller"
        class="mt-12 flex snap-x snap-mandatory gap-5 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <article
          v-for="product in products"
          :key="product.id"
          class="group relative w-[300px] shrink-0 snap-start overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-6 transition hover:border-brand/40 hover:bg-white/[0.05]"
        >
          <div class="flex items-start justify-between">
            <span
              class="grid h-12 w-12 place-items-center rounded-xl font-display text-lg font-bold text-white shadow-lg"
              :style="{ backgroundColor: product.color || '#9A0036' }"
            >
              {{ (product.code || product.name || '?').slice(0, 2).toUpperCase() }}
            </span>
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
              :class="tone(product.statusColor).soft"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="tone(product.statusColor).dot"
              />
              {{ product.status }}
            </span>
          </div>

          <h3 class="mt-5 font-display text-lg font-bold text-white">
            {{ product.name }}
          </h3>
          <p
            v-if="product.code"
            class="mt-1 text-xs font-medium uppercase tracking-wide text-white/40"
          >
            {{ product.code }}
          </p>

          <div class="mt-6">
            <div class="flex items-center justify-between text-[12px] text-white/55">
              <span>Tiến độ</span>
              <span class="font-semibold text-white">{{ product.progress }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
              <div
                class="h-full rounded-full bg-gradient-to-r from-brand to-[#ff4d8d] transition-all duration-700"
                :style="{ width: `${product.progress}%` }"
              />
            </div>
          </div>
        </article>
      </div>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có sản phẩm nào được ghi nhận. Dữ liệu sẽ hiển thị khi có dự án vận hành.
      </p>

      <div class="mt-8">
        <Link
          href="/projects"
          class="inline-flex items-center gap-2 text-sm font-semibold text-brand transition hover:text-[#ff4d8d]"
        >
          Xem toàn bộ dự án
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </Link>
      </div>
    </div>
  </section>
</template>
