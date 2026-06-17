<script setup>
import { onMounted, ref } from 'vue';
import {
    hasFinePointer,
    isPointerOverCongngheHeader,
    prefersReducedMotionNow,
    onSharedPointer,
    onSharedRaf,
} from './motion.js';

/**
 * Con trỏ "sống" cho landing /congnghe: nhiều lớp đi theo chuột với độ trễ khác
 * nhau ⇒ khi di nhanh các lớp tản ra thành vệt sao chổi, khi dừng thì tụ lại.
 * Gồm: quầng sáng thương hiệu lớn (chậm), 4 đốm đuôi (cyan→tím→hồng) và một
 * vòng "reticle" quét quanh đầu con trỏ. Tắt hoàn toàn khi reduced-motion /
 * không có chuột (cảm ứng).
 *
 * Hiệu năng: KHÔNG dùng reactive cho vị trí — ghi thẳng transform vào DOM trong
 * rAF DÙNG CHUNG toàn trang (không re-render Vue mỗi frame), 1 mousemove dùng chung.
 */
const active = ref(false);
const enabled = ref(false);

const auraEl = ref(null);
const headEl = ref(null);
const trailEls = ref([]);

const target = { x: -9999, y: -9999 };
const aura = { x: -9999, y: -9999 };
const head = { x: -9999, y: -9999 };
const trail = [
    { x: -9999, y: -9999, ease: 0.34, size: 13, glow: 'rgba(34,211,238,0.85)' },
    { x: -9999, y: -9999, ease: 0.24, size: 10, glow: 'rgba(167,139,250,0.8)' },
    { x: -9999, y: -9999, ease: 0.17, size: 8, glow: 'rgba(255,77,141,0.75)' },
    { x: -9999, y: -9999, ease: 0.11, size: 6, glow: 'rgba(255,77,141,0.5)' },
];

function shift(p, half) {
    return `translate3d(${(p.x - half).toFixed(1)}px, ${(p.y - half).toFixed(1)}px, 0)`;
}

function frame() {
    if (!enabled.value || !active.value) return;
    head.x += (target.x - head.x) * 0.42;
    head.y += (target.y - head.y) * 0.42;
    aura.x += (target.x - aura.x) * 0.1;
    aura.y += (target.y - aura.y) * 0.1;
    for (const d of trail) {
        d.x += (target.x - d.x) * d.ease;
        d.y += (target.y - d.y) * d.ease;
    }

    if (auraEl.value) auraEl.value.style.transform = shift(aura, 220);
    if (headEl.value) headEl.value.style.transform = shift(head, 28);
    for (let i = 0; i < trail.length; i += 1) {
        const el = trailEls.value[i];
        if (el) el.style.transform = shift(trail[i], trail[i].size * 3);
    }
}

if (!prefersReducedMotionNow()) {
    onSharedPointer((p) => {
        if (!enabled.value) return;
        if (isPointerOverCongngheHeader(p.y)) {
            active.value = false;
            return;
        }
        target.x = p.x;
        target.y = p.y;
        active.value = true;
    });
    onSharedRaf(frame);
}

onMounted(() => {
    enabled.value = hasFinePointer() && !prefersReducedMotionNow();
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
      ref="auraEl"
      class="absolute h-[440px] w-[440px] rounded-full opacity-60 blur-[64px] will-change-transform"
      style="background: radial-gradient(circle, rgba(154,0,54,0.24), transparent 65%);"
    />

    <!-- Đuôi sao chổi -->
    <div
      v-for="(d, i) in trail"
      :key="`trail-${i}`"
      :ref="(el) => (trailEls[i] = el)"
      class="absolute rounded-full blur-[6px] will-change-transform"
      :style="{
        width: `${d.size * 6}px`,
        height: `${d.size * 6}px`,
        background: `radial-gradient(circle, ${d.glow}, transparent 68%)`,
      }"
    />

    <!-- Reticle quét quanh đầu con trỏ -->
    <div
      ref="headEl"
      class="absolute h-14 w-14 will-change-transform"
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
