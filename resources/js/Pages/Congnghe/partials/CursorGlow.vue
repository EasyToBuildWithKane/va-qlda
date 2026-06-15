<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { hasFinePointer, prefersReducedMotionNow } from './motion.js';

const x = ref(-9999);
const y = ref(-9999);
const active = ref(false);
let raf = null;
let tx = -9999;
let ty = -9999;

function onMove(e) {
    tx = e.clientX;
    ty = e.clientY;
    active.value = true;
}

function loop() {
    x.value += (tx - x.value) * 0.18;
    y.value += (ty - y.value) * 0.18;
    raf = requestAnimationFrame(loop);
}

onMounted(() => {
    if (!hasFinePointer() || prefersReducedMotionNow()) return;
    window.addEventListener('mousemove', onMove, { passive: true });
    raf = requestAnimationFrame(loop);
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onMove);
    if (raf) cancelAnimationFrame(raf);
});
</script>

<template>
  <div
    v-show="active"
    class="pointer-events-none fixed -z-[5] h-[420px] w-[420px] rounded-full opacity-60 blur-[60px] transition-opacity"
    style="background: radial-gradient(circle, rgba(154,0,54,0.22), transparent 65%);"
    :style="{ transform: `translate3d(${x - 210}px, ${y - 210}px, 0)` }"
  />
</template>
