<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    name: { type: String, required: true },
    meta: { type: String, default: '' },
    fileCount: { type: Number, default: 0 },
    subfolderCount: { type: Number, default: 0 },
    icon: { type: String, default: 'folder' },
    active: { type: Boolean, default: false },
    canDrag: { type: Boolean, default: false },
    dropActive: { type: Boolean, default: false },
});

const emit = defineEmits([
    'click',
    'contextmenu',
    'drag-start',
    'drag-end',
    'drag-over',
    'drag-leave',
    'drop',
]);
</script>

<template>
  <div
    class="doc-folder-card group relative flex w-full min-w-0 flex-col overflow-hidden rounded-xl border bg-white text-left transition duration-150 dark:bg-slate-900"
    :class="[
      active
        ? 'border-brand/35 shadow-sm ring-2 ring-brand/15'
        : 'border-slate-200/90 hover:border-amber-300/80 hover:shadow-md dark:border-slate-700 dark:hover:border-amber-700/50',
      dropActive ? 'border-brand ring-2 ring-brand/25 bg-brand/[0.04]' : '',
      canDrag ? 'cursor-grab active:cursor-grabbing' : '',
    ]"
    :draggable="canDrag"
    @dragstart="canDrag && emit('drag-start', $event)"
    @dragend="emit('drag-end')"
    @dragover.prevent="emit('drag-over', $event)"
    @dragleave="emit('drag-leave', $event)"
    @drop.prevent="emit('drop', $event)"
    @contextmenu.prevent="emit('contextmenu', $event)"
  >
    <button
      type="button"
      class="flex min-w-0 flex-1 items-start gap-3 px-3 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand/30"
      @click="emit('click')"
    >
      <span class="relative grid h-11 w-11 shrink-0 place-items-center">
        <span class="absolute inset-0 rounded-xl bg-gradient-to-br from-amber-100 to-amber-50 shadow-sm ring-1 ring-amber-200/80 dark:from-amber-900/50 dark:to-amber-950/40 dark:ring-amber-700/40" />
        <span
          class="absolute -bottom-0.5 left-1.5 right-1.5 h-1.5 rounded-b-md bg-amber-300/70 dark:bg-amber-700/50"
          aria-hidden="true"
        />
        <AppIcon
          :name="icon || 'folder'"
          :size="22"
          class="relative text-amber-600 dark:text-amber-400"
        />
      </span>

      <span class="min-w-0 flex-1 pt-0.5">
        <span class="block break-words text-sm font-semibold leading-snug text-slate-800 dark:text-slate-100">
          {{ name }}
        </span>
        <span class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
          <span
            v-if="meta"
            class="tabular-nums text-slate-400"
          >{{ meta }}</span>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-md bg-slate-50 px-1.5 py-0.5 tabular-nums ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700"
          >
            <AppIcon
              name="documents"
              :size="10"
              class="text-sky-500"
            />
            {{ fileCount }}
          </span>
          <span
            v-if="!meta && subfolderCount > 0"
            class="inline-flex items-center gap-1 rounded-md bg-slate-50 px-1.5 py-0.5 tabular-nums ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700"
          >
            <AppIcon
              name="folder"
              :size="10"
              class="text-amber-500"
            />
            {{ subfolderCount }}
          </span>
        </span>
      </span>
    </button>
  </div>
</template>
