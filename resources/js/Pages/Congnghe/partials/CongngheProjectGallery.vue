<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    images: { type: Array, default: () => [] },
    /** slide = panel dự án/sản phẩm; modal = chi tiết */
    density: {
        type: String,
        default: 'slide',
        validator: (v) => ['slide', 'modal'].includes(v),
    },
    emptyHint: {
        type: String,
        default: 'Chưa có hình ảnh tham chiếu. Ảnh được cập nhật từ tab Tài liệu dự án → «Hình ảnh cổng Công nghệ».',
    },
});

const selectedIndex = ref(0);

const list = computed(() => (props.images ?? []).filter((img) => img?.url));

watch(
    () => list.value.map((i) => i.id).join(','),
    () => {
        selectedIndex.value = 0;
    },
);

const active = computed(() => list.value[selectedIndex.value] ?? null);

const previewHeightClass = computed(() =>
    props.density === 'modal' ? 'h-[min(42vh,280px)]' : 'h-40 sm:h-44',
);

function select(index) {
    if (index < 0 || index >= list.value.length) {
        return;
    }
    selectedIndex.value = index;
}
</script>

<template>
  <div class="flex min-h-0 flex-col">
    <p
      v-if="density === 'slide'"
      class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40"
    >
      Hình ảnh tham chiếu
    </p>

    <div
      v-if="active"
      class="mt-3 overflow-hidden rounded-2xl border border-white/10 bg-black/30"
      :class="density === 'modal' ? 'mt-4' : ''"
    >
      <div
        class="relative w-full overflow-hidden bg-black/40"
        :class="previewHeightClass"
      >
        <img
          :key="active.id"
          :src="active.url"
          :alt="active.caption || 'Hình ảnh dự án'"
          class="h-full w-full object-cover object-center"
          loading="lazy"
          decoding="async"
        >
      </div>
      <p
        v-if="active.caption"
        class="border-t border-white/10 px-3 py-2 text-center text-[11px] leading-snug text-white/55 line-clamp-2"
      >
        {{ active.caption }}
      </p>
    </div>

    <div
      v-else
      class="mt-3 flex flex-col items-center justify-center rounded-2xl border border-dashed border-white/12 bg-white/[0.02] px-4 text-center"
      :class="previewHeightClass"
    >
      <span class="grid h-11 w-11 place-items-center rounded-xl border border-white/15 bg-white/5 text-white/35">
        <svg
          width="22"
          height="22"
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
      <p class="mt-3 max-w-xs text-xs leading-relaxed text-white/45">
        {{ emptyHint }}
      </p>
    </div>

    <div
      v-if="list.length > 1"
      class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-5"
      role="listbox"
      aria-label="Chọn ảnh xem"
    >
      <button
        v-for="(img, idx) in list"
        :key="img.id"
        type="button"
        role="option"
        :aria-selected="idx === selectedIndex"
        class="relative aspect-[4/3] w-full overflow-hidden rounded-lg border-2 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="idx === selectedIndex
          ? 'border-brand shadow-[0_0_16px_-4px_rgba(154,0,54,0.85)]'
          : 'border-white/15 opacity-85 hover:border-white/35 hover:opacity-100'"
        @click="select(idx)"
      >
        <img
          :src="img.url"
          :alt="img.caption || `Ảnh ${idx + 1}`"
          class="h-full w-full object-cover object-center"
          loading="lazy"
          decoding="async"
        >
      </button>
    </div>
  </div>
</template>
