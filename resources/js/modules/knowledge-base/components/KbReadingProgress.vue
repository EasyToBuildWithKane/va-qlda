<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const progress = ref(0);
const visible = ref(false);

function update() {
    const el = document.documentElement;
    const scrollTop = el.scrollTop || document.body.scrollTop;
    const height = el.scrollHeight - el.clientHeight;
    if (height <= 0) {
        progress.value = 0;
        visible.value = false;
        return;
    }
    visible.value = scrollTop > 80;
    progress.value = Math.min(100, Math.max(0, (scrollTop / height) * 100));
}

let raf = null;
function onScroll() {
    if (raf) return;
    raf = requestAnimationFrame(() => {
        update();
        raf = null;
    });
}

onMounted(() => {
    update();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    if (raf) cancelAnimationFrame(raf);
});
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] h-[3px] bg-slate-200/40 dark:bg-slate-800/60"
    :class="visible ? 'opacity-100' : 'opacity-0'"
    role="progressbar"
    :aria-valuenow="Math.round(progress)"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="`Tiến độ đọc ${Math.round(progress)} phần trăm`"
  >
    <div
      class="h-full bg-gradient-to-r from-brand via-rose-600/90 to-brand transition-[width] duration-150 ease-out motion-reduce:transition-none"
      :style="{ width: `${progress}%` }"
    />
  </div>
</template>
