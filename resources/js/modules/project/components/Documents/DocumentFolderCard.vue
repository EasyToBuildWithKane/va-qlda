<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    name: { type: String, required: true },
    meta: { type: String, default: '' },
    fileCount: { type: Number, default: 0 },
    subfolderCount: { type: Number, default: 0 },
    active: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="doc-folder-card group relative flex w-full min-w-0 items-center gap-3.5 overflow-hidden rounded-2xl px-3.5 py-3.5 text-left transition duration-200"
    :class="active
      ? 'bg-gradient-to-br from-sky-100 to-indigo-50 ring-2 ring-sky-300/70 shadow-md shadow-sky-100/80 dark:from-sky-950/70 dark:to-indigo-950/40 dark:ring-sky-700/60 dark:shadow-none'
      : 'bg-gradient-to-br from-[#EEF1FF] to-[#F5F7FF] hover:-translate-y-0.5 hover:from-[#E6EBFF] hover:to-[#EEF2FF] hover:shadow-md hover:shadow-sky-100/70 dark:from-sky-950/35 dark:to-slate-900/40 dark:hover:from-sky-950/55 dark:hover:to-slate-900/60 dark:hover:shadow-none'"
    @click="$emit('click')"
  >
    <span
      class="pointer-events-none absolute -right-4 -top-6 h-20 w-20 rounded-full bg-sky-300/15 blur-2xl transition group-hover:bg-sky-400/25 dark:bg-sky-500/10"
      aria-hidden="true"
    />

    <span class="relative grid h-12 w-12 shrink-0 place-items-center">
      <span
        class="absolute inset-0 rounded-xl bg-white/90 shadow-sm ring-1 ring-sky-200/70 transition group-hover:scale-105 group-hover:ring-sky-300 dark:bg-slate-900/80 dark:ring-sky-800/60"
      />
      <span
        class="absolute -bottom-0.5 left-1 right-1 h-2 rounded-b-md bg-sky-200/70 dark:bg-sky-800/50"
        aria-hidden="true"
      />
      <AppIcon
        name="folder"
        :size="24"
        class="relative text-sky-500 transition group-hover:text-sky-600 dark:text-sky-400"
      />
    </span>

    <span class="relative min-w-0 flex-1">
      <span class="flex items-start gap-2">
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100">
            {{ name }}
          </span>
          <span
            v-if="meta"
            class="mt-1 block truncate text-[11px] tabular-nums text-slate-500 dark:text-slate-400"
          >
            {{ meta }}
          </span>
        </span>
        <AppIcon
          name="chevron-right"
          :size="14"
          class="mt-0.5 shrink-0 text-slate-300 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100 dark:text-slate-500"
        />
      </span>

      <span class="mt-2 flex flex-wrap items-center gap-1.5">
        <span
          class="inline-flex items-center gap-1 rounded-md bg-white/80 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 ring-1 ring-slate-200/70 dark:bg-slate-900/60 dark:text-slate-300 dark:ring-slate-700/70"
        >
          <AppIcon
            name="documents"
            :size="10"
            class="text-sky-500"
          />
          {{ fileCount }} tệp
        </span>
        <span
          v-if="subfolderCount > 0"
          class="inline-flex items-center gap-1 rounded-md bg-white/80 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 ring-1 ring-slate-200/70 dark:bg-slate-900/60 dark:text-slate-300 dark:ring-slate-700/70"
        >
          <AppIcon
            name="folder"
            :size="10"
            class="text-amber-500"
          />
          {{ subfolderCount }} thư mục
        </span>
      </span>
    </span>
  </button>
</template>
