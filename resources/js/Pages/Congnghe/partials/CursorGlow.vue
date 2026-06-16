<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { hasFinePointer, prefersReducedMotionNow } from './motion.js';

/**
 * Con trỏ "sống" cho landing /congnghe: nhiều lớp đi theo chuột với độ trễ khác
 * nhau ⇒ khi di nhanh các lớp tản ra thành vệt sao chổi, khi dừng thì tụ lại.
 * Gồm: quầng sáng thương hiệu lớn (chậm), 4 đốm đuôi (cyan→tím→hồng) và một
 * vòng "reticle" quét quanh đầu con trỏ. Tắt hoàn toàn khi reduced-motion /
 * không có chuột (cảm ứng).
 */
const active = ref(false);
const target = { x: -9999, y: -9999 };

// Quầng nền lớn (ease chậm) — bám trễ tạo cảm giác khối sáng mềm.
const aura = reactive({ x: -9999, y: -9999 });
// Đầu con trỏ (ease nhanh) — neo vòng reticle.
const head = reactive({ x: -9999, y: -9999 });
// Đuôi sao chổi: ease giảm dần ⇒ tản ra khi di chuyển nhanh.
const trail = reactive([
    { x: -9999, y: -9999, ease: 0.34, size: 13, glow: 'rgba(34,211,238,0.85)' },
    { x: -9999, y: -9999, ease: 0.24, size: 10, glow: 'rgba(167,139,250,0.8)' },
    { x: -9999, y: -9999, ease: 0.17, size: 8, glow: 'rgba(255,77,141,0.75)' },
    { x: -9999, y: -9999, ease: 0.11, size: 6, glow: 'rgba(255,77,141,0.5)' },
]);

let raf = null;

function onMove(e) {
    target.x = e.clientX;
    target.y = e.clientY;
    active.value = true;
}

function loop() {
    head.x += (target.x - head.x) * 0.42;
    head.y += (target.y - head.y) * 0.42;
    aura.x += (target.x - aura.x) * 0.1;
    aura.y += (target.y - aura.y) * 0.1;
    for (const d of trail) {
        d.x += (target.x - d.x) * d.ease;
        d.y += (target.y - d.y) * d.ease;
    }
    raf = requestAnimationFrame(loop);
}

function shift(p, half) {
    return `translate3d(${(p.x - half).toFixed(1)}px, ${(p.y - half).toFixed(1)}px, 0)`;
}

onMounted(() => {
    if (!hasFinePointer() || prefersReducedMotionNow()) {
        return;
    }
    window.addEventListener('mousemove', onMove, { passive: true });
    raf = requestAnimationFrame(loop);
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onMove);
    if (raf) {
        cancelAnimationFrame(raf);
    }
});
</script>

<template>
  <div
    v-show="active"
    class="pointer-events-none fixed inset-0 z-[1] overflow-hidden"
    aria-hidden="true"
  >
    <!-- Quầng sáng thương hiệu lớn -->
    <div
      class="absolute h-[440px] w-[440px] rounded-full opacity-60 blur-[64px]"
      style="background: radial-gradient(circle, rgba(154,0,54,0.24), transparent 65%);"
      :style="{ transform: shift(aura, 220) }"
    />

    <!-- Đuôi sao chổi -->
    <div
      v-for="(d, i) in trail"
      :key="`trail-${i}`"
      class="absolute rounded-full blur-[6px]"
      :style="{
        width: `${d.size * 6}px`,
        height: `${d.size * 6}px`,
        background: `radial-gradient(circle, ${d.glow}, transparent 68%)`,
        transform: shift(d, d.size * 3),
      }"
    />

    <!-- Reticle quét quanh đầu con trỏ -->
    <div
      class="absolute h-14 w-14"
      :style="{ transform: shift(head, 28) }"
    >
      <span
        class="absolute inset-0 rounded-full opacity-70"
        style="
          padding: 1.5px;
          background: conic-gradient(from 0deg, transparent 0deg, rgba(34,211,238,0.9) 60deg, transparent 140deg, rgba(255,77,141,0.85) 220deg, transparent 320deg);
          -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
          -webkit-mask-composite: xor;
          mask-composite: exclude;
          animation: cn-spin-slow 5s linear infinite;
        "
      />
      <span class="absolute left-1/2 top-1/2 h-1 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/80 shadow-[0_0_8px_2px_rgba(34,211,238,0.7)]" />
    </div>
  </div>
</template>
