<script setup>
import CongngheBrandImage from './CongngheBrandImage.vue';
import { prefersReducedMotionNow } from './motion.js';

defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: 'Linh vật VAS' },
    /** hero | assistant | inline */
    variant: { type: String, default: 'inline' },
    /** Extra transform from parallax parent */
    parallaxStyle: { type: Object, default: () => ({}) },
});

const reduced = prefersReducedMotionNow();

const sizeClass = {
    hero: 'h-[88%] w-auto max-h-[min(72vw,420px)] sm:max-h-[440px] lg:max-h-[480px] xl:max-h-[520px]',
    assistant: 'h-[3.75rem] w-auto sm:h-[4.25rem] md:h-[5rem] lg:h-[5.75rem]',
    inline: 'h-32 w-auto sm:h-36 md:h-40',
    footer: 'h-36 w-auto sm:h-44 md:h-48 lg:h-52',
};
</script>

<template>
  <div
    class="relative flex items-center justify-center"
    :class="variant === 'hero' ? 'aspect-square w-full max-w-[min(100%,420px)] lg:max-w-[480px] xl:max-w-[520px]' : 'shrink-0'"
  >
    <template v-if="variant === 'hero'">
      <div
        class="absolute inset-0 rounded-full border border-cyan-400/15"
        :class="reduced ? '' : 'animate-cn-spin-slow'"
      />
      <div
        class="absolute inset-[12%] rounded-full border border-white/10"
        :class="reduced ? '' : '[animation:cn-spin-slow_24s_linear_infinite_reverse]'"
      />
      <div class="absolute inset-[24%] rounded-full bg-[radial-gradient(circle,rgba(154,0,54,0.28),transparent_70%)] blur-lg" />
    </template>

    <template v-if="variant === 'assistant'">
      <span
        v-if="!reduced"
        class="pointer-events-none absolute -inset-3 rounded-full border border-brand/25 animate-cn-ping-ring"
        aria-hidden="true"
      />
    </template>

    <span
      class="relative z-10 inline-flex items-center justify-center drop-shadow-[0_8px_24px_rgba(154,0,54,0.35)]"
    >
      <CongngheBrandImage
        :src="src"
        :alt="alt"
        :cutout="false"
        class="object-contain"
        :class="[
          sizeClass[variant] ?? sizeClass.inline,
          reduced ? '' : (variant === 'assistant' ? 'animate-cn-assistant-bob' : 'animate-cn-float'),
        ]"
        :style="parallaxStyle"
        decoding="async"
      />
    </span>
  </div>
</template>
