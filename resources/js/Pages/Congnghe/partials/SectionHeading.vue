<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    center: { type: Boolean, default: false },
});
</script>

<template>
  <div :class="['max-w-2xl', center && 'mx-auto text-center']">
    <p
      v-if="eyebrow"
      class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 font-mono text-[11px] font-medium uppercase tracking-[0.22em] text-white/70 backdrop-blur"
    >
      <span class="h-1.5 w-1.5 rounded-full bg-brand shadow-[0_0_8px_2px_rgba(255,77,141,0.6)]" />
      {{ eyebrow }}
    </p>
    <h2 class="mt-5 font-display text-[1.65rem] font-extrabold leading-[1.12] tracking-tight text-white sm:text-4xl lg:text-[2.7rem]">
      {{ title }}
    </h2>
    <p
      v-if="subtitle"
      class="mt-4 text-base leading-relaxed text-white/55"
      :class="center && 'mx-auto'"
    >
      {{ subtitle }}
    </p>
  </div>
</template>
