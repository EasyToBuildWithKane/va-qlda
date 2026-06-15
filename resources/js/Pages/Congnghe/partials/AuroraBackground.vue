<script setup>
import { onMounted, ref, watch } from 'vue';
import { usePrefersReducedMotion, hasFinePointer } from './motion.js';
import ParticleField from './ParticleField.vue';
import WebGLBackdrop from './WebGLBackdrop.vue';

/**
 * Bộ chọn nền cố định cho landing /congnghe. Lớp CSS (aurora blobs + grid +
 * vignette) luôn có mặt làm nền tảng & fallback. Trên đó:
 *  - WebGLBackdrop (shader aurora + point cloud) khi WebGL khả dụng, không
 *    reduced-motion, không low-power;
 *  - ParticleField (canvas 2D neural net) làm fallback khi WebGL không có;
 *  - chỉ CSS khi reduced-motion.
 */
const reduced = usePrefersReducedMotion();
const mode = ref('css'); // 'webgl' | 'particles' | 'css'

function probeWebgl() {
    try {
        const c = document.createElement('canvas');
        return !!(c.getContext('webgl2') || c.getContext('webgl') || c.getContext('experimental-webgl'));
    } catch {
        return false;
    }
}

function decide() {
    if (reduced.value) {
        mode.value = 'css';
        return;
    }
    const small = window.matchMedia?.('(max-width: 639px)').matches === true;
    const weak = !hasFinePointer() && (navigator.hardwareConcurrency || 8) <= 4;
    if (!small && !weak && probeWebgl()) mode.value = 'webgl';
    else mode.value = 'particles';
}

function onWebglFail() {
    mode.value = 'particles';
}

onMounted(decide);
watch(reduced, decide);
</script>

<template>
  <div
    class="pointer-events-none fixed inset-0 z-0 overflow-hidden bg-[#05060c]"
    aria-hidden="true"
  >
    <!-- Aurora mesh blobs -->
    <div class="absolute -left-[12%] -top-[18%] h-[60vmax] w-[60vmax] rounded-full bg-[radial-gradient(circle,rgba(154,0,54,0.40),transparent_62%)] blur-[60px] animate-cn-aurora" />
    <div class="absolute -right-[14%] top-[6%] h-[52vmax] w-[52vmax] rounded-full bg-[radial-gradient(circle,rgba(124,58,237,0.30),transparent_62%)] blur-[60px] animate-cn-aurora-slow" />
    <div class="absolute bottom-[-22%] left-[18%] h-[56vmax] w-[56vmax] rounded-full bg-[radial-gradient(circle,rgba(6,182,212,0.20),transparent_64%)] blur-[70px] animate-cn-aurora" />
    <div class="absolute bottom-[-10%] right-[8%] h-[44vmax] w-[44vmax] rounded-full bg-[radial-gradient(circle,rgba(255,77,141,0.20),transparent_64%)] blur-[70px] animate-cn-aurora-slow" />

    <!-- Grid overlay -->
    <div
      class="absolute inset-0 opacity-[0.55] animate-cn-grid-pan"
      style="
        background-image:
          linear-gradient(to right, rgba(255,255,255,0.045) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(255,255,255,0.045) 1px, transparent 1px);
        background-size: 40px 40px;
        mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 30%, transparent 80%);
        -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 30%, transparent 80%);
      "
    />

    <!-- Lớp động: WebGL (ưu tiên) hoặc neural net canvas 2D (fallback) -->
    <WebGLBackdrop
      v-if="mode === 'webgl'"
      @fail="onWebglFail"
    />
    <ParticleField v-else-if="mode === 'particles'" />

    <!-- Vignette + grain -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_55%,rgba(5,6,12,0.75)_100%)]" />
    <div class="absolute inset-0 bg-gradient-to-b from-[#05060c] via-transparent to-[#05060c]" />
  </div>
</template>
