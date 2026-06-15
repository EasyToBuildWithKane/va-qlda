<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import MagneticButton from './MagneticButton.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { tone } from './tones.js';

defineProps({
    products: { type: Array, default: () => [] },
});

const page = usePage();
const portal = computed(() => page.props.portal ?? { canEnterQlda: false, qldaHome: '/dashboard' });

const scroller = ref(null);

function scrollBy(dir) {
    scroller.value?.scrollBy({ left: dir * 360, behavior: 'smooth' });
}
</script>

<template>
  <section
    id="san-pham"
    class="relative py-28"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <div class="flex flex-wrap items-end justify-between gap-6">
        <SectionHeading
          eyebrow="Hệ sinh thái sản phẩm"
          title="Những nền tảng đã hoàn thành"
          subtitle="Các sản phẩm đã nghiệm thu và đưa vào vận hành — thành quả do Phòng Công Nghệ phát triển, phục vụ trực tiếp công việc hằng ngày."
        />
        <div
          v-if="products.length"
          class="hidden gap-2 sm:flex"
        >
          <button
            v-for="(dir, idx) in [-1, 1]"
            :key="idx"
            type="button"
            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition hover:border-brand/40 hover:bg-white/10 hover:text-white"
            :aria-label="dir < 0 ? 'Trước' : 'Sau'"
            @click="scrollBy(dir)"
          >
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path :d="dir < 0 ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6'" /></svg>
          </button>
        </div>
      </div>

      <div
        v-if="products.length"
        ref="scroller"
        class="mt-12 flex snap-x snap-mandatory gap-5 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <GlassCard
          v-for="product in products"
          :key="product.id"
          tilt
          :padded="false"
          class="flex w-[330px] shrink-0 snap-start flex-col p-6"
        >
          <div class="flex items-start justify-between">
            <span
              class="grid h-12 w-12 place-items-center rounded-xl font-display text-lg font-bold text-white shadow-lg ring-1 ring-white/20"
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
            class="mt-1 font-mono text-[11px] uppercase tracking-wide text-white/40"
          >
            {{ product.code }}
          </p>
          <p
            class="mt-3 text-[12.5px] leading-relaxed text-white/50"
            style="display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;"
          >
            {{ product.description || 'Nền tảng đã hoàn thành và đưa vào vận hành.' }}
          </p>

          <div class="mt-5">
            <div class="flex items-center justify-between font-mono text-[11px] text-white/55">
              <span>TIẾN ĐỘ</span>
              <span class="font-semibold text-white">{{ product.progress }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
              <div
                class="h-full rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d,#9A0036)] bg-[length:200%_100%] animate-cn-shimmer"
                :style="{ width: `${product.progress}%` }"
              />
            </div>
          </div>

          <div class="mt-5 flex items-center gap-2.5 border-t border-white/10 pt-4">
            <template v-if="product.manager">
              <Avatar
                :name="product.manager.name"
                :src="product.manager.avatar"
                :size="32"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-[12.5px] font-semibold text-white">
                  {{ product.manager.name }}
                </p>
                <p class="truncate text-[11px] text-white/45">
                  {{ product.manager.role_title || 'Phụ trách chính' }}
                </p>
              </div>
            </template>
            <p
              v-else
              class="text-[12px] text-white/35"
            >
              Chưa phân công phụ trách
            </p>
          </div>
        </GlassCard>
      </div>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có dự án nào hoàn thành. Dữ liệu sẽ hiển thị khi có sản phẩm được nghiệm thu.
      </p>

      <div
        v-if="portal.canEnterQlda"
        class="mt-8"
      >
        <MagneticButton
          href="/projects"
          inertia
          variant="ghost"
          class="!px-5 !py-2.5 !text-[13px]"
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
        </MagneticButton>
      </div>
    </div>
  </section>
</template>
