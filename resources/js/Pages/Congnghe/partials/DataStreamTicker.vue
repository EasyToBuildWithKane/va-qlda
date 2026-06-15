<script setup>
import { computed } from 'vue';
import { useDataStream, prefersReducedMotionNow } from './motion.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    // 'marquee' (cuộn ngang liền mạch) | 'cycle' (đổi từng dòng theo chu kỳ)
    variant: { type: String, default: 'marquee' },
    // giây cho 1 vòng marquee
    speed: { type: Number, default: 28 },
});

const reduced = prefersReducedMotionNow();
const { current } = useDataStream({ values: () => props.items, interval: 2600 });

// marquee cần nhân đôi nội dung để cuộn -50% liền mạch
const marqueeItems = computed(() => (reduced ? props.items : [...props.items, ...props.items]));
const marqueeStyle = computed(() => (reduced ? {} : { animation: `cn-ticker-x ${props.speed}s linear infinite` }));
</script>

<template>
  <div class="font-mono text-[11px] tracking-wide text-white/55">
    <div
      v-if="variant === 'marquee'"
      class="relative flex overflow-hidden"
      role="presentation"
    >
      <div
        class="flex shrink-0 items-center gap-6 pr-6"
        :class="reduced ? 'flex-wrap' : 'whitespace-nowrap'"
        :style="marqueeStyle"
      >
        <span
          v-for="(item, i) in marqueeItems"
          :key="i"
          class="inline-flex items-center gap-2"
        >
          <span class="h-1 w-1 rounded-full bg-cyan-300/70" />{{ item }}
        </span>
      </div>
    </div>
    <div
      v-else
      class="flex items-center gap-2"
    >
      <span class="h-1.5 w-1.5 animate-cn-glow rounded-full bg-cyan-300/80" />
      <span :key="current">{{ current }}</span>
    </div>
  </div>
</template>
