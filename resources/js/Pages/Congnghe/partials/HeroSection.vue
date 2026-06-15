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

const chips = [
    { label: 'AI', cls: 'left-1/2 top-1 -translate-x-1/2', depth: -28 },
    { label: 'Data', cls: 'right-0 top-1/3', depth: 34 },
    { label: 'Cloud', cls: 'bottom-2 left-1/2 -translate-x-1/2', depth: -22 },
    { label: 'Web', cls: 'left-0 top-1/2', depth: 26 },
];

const highlights = [
    { key: 'projects', label: 'Dự án', suffix: '+' },
    { key: 'orgPeople', label: 'Nhân sự sơ đồ', suffix: '' },
    { key: 'departments', label: 'Phòng ban', suffix: '' },
];
</script>

<template>
  <section
    id="top"
    class="relative flex min-h-screen items-center overflow-hidden pt-24"
  >
    <CongngheBrandBackdrop
      variant="dragon"
      align="center"
      opacity-class="opacity-[0.04]"
    />
    <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-brand/15 to-transparent" />
    <div class="relative mx-auto grid w-full max-w-7xl items-center gap-12 px-5 pb-16 sm:px-8 lg:grid-cols-[1.1fr_0.9fr]">
      <!-- Copy -->
      <div
        class="transition-all duration-700"
        :class="ready ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
      >
        <span class="inline-flex flex-wrap items-center gap-2.5 rounded-full border border-white/15 bg-white/5 py-1.5 pl-2 pr-3.5 font-mono text-[11.5px] font-medium tracking-wide text-white/80 backdrop-blur">
          <img
            :src="congngheBrand.badgeCircle"
            alt=""
            class="h-7 w-7 object-contain"
            decoding="async"
          >
          <span class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70" />
            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
          </span>
          AI-NATIVE · PHÒNG CÔNG NGHỆ VAS
        </span>

        <h1 class="mt-6 font-display text-[2.6rem] font-extrabold leading-[1.04] tracking-tight text-white sm:text-6xl">
          Kiến tạo
          <span class="relative bg-gradient-to-r from-[#ff9ec4] via-[#ff4d8d] to-[#7c3aed] bg-clip-text text-transparent">nền tảng số</span>
          cho giáo dục tương lai
        </h1>

        <p class="mt-6 max-w-xl text-base leading-relaxed text-white/60 sm:text-lg">
          Phòng Công Nghệ xây dựng hạ tầng dữ liệu, sản phẩm phần mềm và năng lực AI
          phục vụ toàn hệ thống — biến mỗi quy trình thành sản phẩm thật, vận hành và
          đo lường được.
        </p>

        <div class="mt-9 flex flex-wrap items-center gap-3">
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

        <dl class="mt-12 grid max-w-md grid-cols-3 gap-6">
          <div
            v-for="h in highlights"
            :key="h.key"
          >
            <dt class="font-display text-3xl font-bold text-white sm:text-4xl">
              <CountStat
                :value="Number(metrics[h.key] ?? 0)"
                :active="ready"
              /><span class="bg-gradient-to-r from-brand to-[#ff4d8d] bg-clip-text text-transparent">{{ h.suffix }}</span>
            </dt>
            <dd class="mt-1 font-mono text-[11px] uppercase tracking-wider text-white/45">
              {{ h.label }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Brand hero visual -->
      <div
        class="relative mx-auto hidden aspect-square w-full max-w-md lg:block"
        :style="layer(-10)"
      >
        <div class="absolute inset-0 rounded-full border border-brand/20 animate-cn-spin-slow" />
        <div class="absolute inset-[10%] rounded-full border border-white/10 [animation:cn-spin-slow_22s_linear_infinite_reverse]" />
        <div class="absolute inset-[22%] rounded-full border border-dashed border-brand/25 animate-cn-spin-slow" />
        <div class="absolute inset-[18%] rounded-full bg-[radial-gradient(circle,rgba(154,0,54,0.35),transparent_70%)] blur-xl animate-cn-glow" />

        <div
          class="absolute inset-0 flex items-end justify-center pb-2"
          :style="layer(14)"
        >
          <img
            :src="congngheBrand.mascotWave"
            alt="Linh vật VAS — chào mừng đến Phòng Công Nghệ"
            class="relative z-10 h-[88%] w-auto max-w-none object-contain drop-shadow-[0_32px_64px_rgba(154,0,54,0.35)] animate-cn-float"
            width="420"
            height="420"
            decoding="async"
          >
        </div>

        <img
          :src="congngheBrand.dragonSilhouette"
          alt=""
          class="pointer-events-none absolute left-1/2 top-[8%] h-24 w-auto -translate-x-1/2 opacity-40"
          :style="layer(8)"
          loading="lazy"
        >

        <span
          v-for="chip in chips"
          :key="chip.label"
          class="absolute rounded-xl border border-white/10 bg-white/[0.06] px-3 py-1.5 font-mono text-xs font-medium text-white/85 backdrop-blur-xl animate-cn-float"
          :class="chip.cls"
          :style="layer(chip.depth)"
        >{{ chip.label }}</span>
      </div>
    </div>

    <a
      href="#gioi-thieu"
      class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/35 transition hover:text-white"
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
