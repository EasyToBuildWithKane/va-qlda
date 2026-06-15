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
      class="inline-flex items-center gap-2 text-[12.5px] font-semibold uppercase tracking-[0.18em] text-brand"
    >
      <span class="h-px w-6 bg-brand" />
      {{ eyebrow }}
    </p>
    <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
      {{ title }}
    </h2>
    <p
      v-if="subtitle"
      class="mt-4 text-base leading-relaxed text-white/55"
    >
      {{ subtitle }}
    </p>
  </div>
</template>
