<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    icon: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
  <section class="profile-panel overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
    <header class="profile-panel__head flex items-start gap-3 border-b border-slate-100 px-5 py-4">
      <div
        class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-900 text-white shadow-sm ring-1 ring-slate-900/10"
        aria-hidden="true"
      >
        <AppIcon
          :name="icon"
          :size="16"
        />
      </div>
      <div class="min-w-0 pt-0.5">
        <h2 class="font-display text-sm font-semibold tracking-tight text-slate-900">
          {{ title }}
        </h2>
        <p
          v-if="subtitle"
          class="mt-0.5 text-[12px] leading-snug text-slate-500"
        >
          {{ subtitle }}
        </p>
      </div>
    </header>
    <div class="profile-panel__body">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.profile-panel__head {
    background: linear-gradient(180deg, rgb(248 250 252 / 0.95) 0%, rgb(255 255 255) 100%);
}
</style>
