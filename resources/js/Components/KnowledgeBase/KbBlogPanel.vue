<script setup>
defineProps({
    ariaLabel: { type: String, required: true },
    title: { type: String, default: '' },
    /** tags | default */
    variant: { type: String, default: 'default' },
    flushBody: { type: Boolean, default: false },
});
</script>

<template>
  <section
    class="kb-blog-panel"
    :class="variant === 'tags' ? 'kb-blog-panel--tags' : ''"
    :aria-label="ariaLabel"
  >
    <header
      v-if="title || $slots['head-actions']"
      class="kb-blog-panel__header"
    >
      <p
        v-if="title"
        class="kb-blog-panel__eyebrow"
      >
        {{ title }}
      </p>
      <slot name="head-actions" />
    </header>
    <div
      class="kb-blog-panel__body"
      :class="flushBody ? 'kb-blog-panel__body--flush' : ''"
    >
      <slot />
    </div>
  </section>
</template>

<style scoped>
.kb-blog-panel {
  @apply overflow-hidden rounded-card border border-slate-200/70 bg-white shadow-sm;
}

.kb-blog-panel--tags {
  @apply border-slate-200/80 bg-gradient-to-b from-slate-50/40 to-white;
}

.kb-blog-panel__header {
  @apply flex min-h-[2.75rem] items-center justify-between gap-2 border-b border-slate-100 px-3.5 py-2 sm:px-4;
}

.kb-blog-panel__eyebrow {
  @apply font-display text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400;
}

.kb-blog-panel__body {
  @apply px-3.5 py-3 sm:px-4 sm:py-3.5;
}

.kb-blog-panel__body--flush {
  @apply p-0;
}
</style>
