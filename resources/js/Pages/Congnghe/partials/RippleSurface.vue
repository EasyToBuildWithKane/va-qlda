<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRipple } from './motion.js';

/**
 * Lớp gợn sóng tự gắn vào phần tử cha (host). Đặt làm con của một phần tử
 * position:relative (vd #overlay của GlassCard) — nó tự lắng nghe pointerdown
 * trên host và vẽ ripple. Reduced-motion ⇒ no-op (useRipple tự bỏ qua).
 */
const props = defineProps({
    color: { type: String, default: 'rgba(255,255,255,0.16)' },
});

const root = ref(null);
const { bind, ripples } = useRipple();
let host = null;

onMounted(() => {
    host = root.value?.parentElement ?? null;
    host?.addEventListener('pointerdown', bind);
});

onBeforeUnmount(() => host?.removeEventListener('pointerdown', bind));
</script>

<template>
  <span
    ref="root"
    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
    aria-hidden="true"
  >
    <span
      v-for="r in ripples"
      :key="r.id"
      class="absolute animate-cn-ripple rounded-full"
      :style="{ ...r.style, background: props.color }"
    />
  </span>
</template>
