<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import MagneticButton from './MagneticButton.vue';
import CountStat from './CountStat.vue';
import { hasFinePointer, prefersReducedMotionNow } from './motion.js';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';

defineProps({
    metrics: { type: Object, default: () => ({}) },
});

const px = ref(0);
const py = ref(0);
const ready = ref(false);

function onMove(e) {
    px.value = (e.clientX / window.innerWidth - 0.5);
    py.value = (e.clientY / window.innerHeight - 0.5);
}

function layer(depth) {
    return { transform: `translate3d(${(px.value * depth).toFixed(1)}px, ${(py.value * depth).toFixed(1)}px, 0)` };
}

onMounted(() => {
    requestAnimationFrame(() => (ready.value = true));
    if (hasFinePointer() && !prefersReducedMotionNow()) {
        window.addEventListener('mousemove', onMove, { passive: true });
    }
});
onBeforeUnmount(() => window.removeEventListener('mousemove', onMove));

const highlights = [
    { key: 'projects', label: 'Dự án', suffix: '+' },
    { key: 'orgPeople', label: 'Nhân sự sơ đồ', suffix: '' },
    { key: 'departments', label: 'Phòng ban', suffix: '' },
];
</script>

<template>
  <section
    id="top"
    class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-5 pb-16 pt-28 text-center sm:px-8"
  >
    <CongngheBrandBackdrop
      variant="dragon"
      align="center"
      opacity-class="opacity-[0.03]"
    />
    <div class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-brand/12 via-cyan-500/5 to-transparent" />

    <div
      class="relative z-10 mx-auto flex w-full max-w-3xl flex-col items-center transition-all duration-700"
      :class="ready ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
    >
      <h1 class="font-display text-[2.35rem] font-extrabold leading-[1.06] tracking-tight text-white sm:text-5xl lg:text-[3.25rem]">
        Kiến tạo
        <span class="bg-gradient-to-r from-cyan-200 via-brand-200 to-violet-300 bg-clip-text text-transparent">nền tảng số</span>
        cho giáo dục tương lai
      </h1>

      <p class="mt-5 max-w-xl text-base leading-relaxed text-white/60 sm:text-lg">
        Phòng Công Nghệ xây dựng hạ tầng dữ liệu, sản phẩm phần mềm và năng lực AI
        phục vụ toàn hệ thống — biến mỗi quy trình thành sản phẩm thật, vận hành và
        đo lường được.
      </p>

      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <MagneticButton
          href="#san-pham"
          variant="primary"
        >
          Khám phá hệ sinh thái
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </MagneticButton>
        <MagneticButton
          href="#to-chuc"
          variant="ghost"
        >
          Đội ngũ &amp; tổ chức
        </MagneticButton>
      </div>

      <!-- Mascot — nhỏ, trung tâm -->
      <div
        class="relative mx-auto mt-10 flex aspect-square w-full max-w-[220px] items-center justify-center sm:max-w-[260px]"
        :style="layer(-6)"
      >
        <div class="absolute inset-0 rounded-full border border-cyan-400/15 animate-cn-spin-slow" />
        <div class="absolute inset-[14%] rounded-full border border-white/10 [animation:cn-spin-slow_24s_linear_infinite_reverse]" />
        <div class="absolute inset-[28%] rounded-full bg-[radial-gradient(circle,rgba(154,0,54,0.28),transparent_70%)] blur-lg" />
        <img
          :src="congngheBrand.mascotWave"
          alt="Linh vật VAS — Phòng Công Nghệ"
          class="relative z-10 h-[72%] w-auto object-contain drop-shadow-[0_20px_40px_rgba(154,0,54,0.35)] animate-cn-float"
          width="260"
          height="260"
          decoding="async"
          :style="layer(10)"
        >
      </div>

      <dl class="mt-10 grid w-full max-w-lg grid-cols-3 gap-4 sm:gap-6">
        <div
          v-for="h in highlights"
          :key="h.key"
        >
          <dt class="font-display text-2xl font-bold text-white sm:text-3xl">
            <CountStat
              :value="Number(metrics[h.key] ?? 0)"
              :active="ready"
            /><span class="bg-gradient-to-r from-brand to-cyan-300/80 bg-clip-text text-transparent">{{ h.suffix }}</span>
          </dt>
          <dd class="mt-1 font-mono text-[10px] uppercase tracking-wider text-white/45">
            {{ h.label }}
          </dd>
        </div>
      </dl>
    </div>

    <a
      href="#gioi-thieu"
      class="absolute bottom-6 left-1/2 z-10 -translate-x-1/2 text-white/35 transition hover:text-white"
      aria-label="Cuộn xuống"
    >
      <svg
        width="26"
        height="26"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        class="animate-bounce"
      ><path d="M12 5v14M6 13l6 6 6-6" /></svg>
    </a>
  </section>
</template>
