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
    /** violet (default) | sky | emerald | rose | amber | slate */
    tone: { type: String, default: 'violet' },
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits([
    'click',
    'contextmenu',
    'toggle-select',
    'drag-start',
    'drag-end',
    'drag-over',
    'drag-leave',
    'drop',
]);

const chipBg = {
    violet: 'bg-gradient-to-br from-violet-100 to-violet-50 ring-violet-200/90 dark:from-violet-900/55 dark:to-violet-950/40 dark:ring-violet-700/45',
    sky: 'bg-gradient-to-br from-sky-100 to-sky-50 ring-sky-200/90 dark:from-sky-900/55 dark:to-sky-950/40 dark:ring-sky-700/45',
    emerald: 'bg-gradient-to-br from-emerald-100 to-emerald-50 ring-emerald-200/90 dark:from-emerald-900/55 dark:to-emerald-950/40 dark:ring-emerald-700/45',
    rose: 'bg-gradient-to-br from-rose-100 to-rose-50 ring-rose-200/90 dark:from-rose-900/55 dark:to-rose-950/40 dark:ring-rose-700/45',
    amber: 'bg-gradient-to-br from-amber-100 to-amber-50 ring-amber-200/90 dark:from-amber-900/55 dark:to-amber-950/40 dark:ring-amber-700/45',
    slate: 'bg-gradient-to-br from-slate-100 to-white ring-slate-200/90 dark:from-slate-800 dark:to-slate-900 dark:ring-slate-600',
};

const chipIcon = {
    violet: 'text-violet-700 dark:text-violet-300',
    sky: 'text-sky-700 dark:text-sky-300',
    emerald: 'text-emerald-700 dark:text-emerald-300',
    rose: 'text-rose-700 dark:text-rose-300',
    amber: 'text-amber-700 dark:text-amber-300',
    slate: 'text-slate-600 dark:text-slate-300',
};

const surface = {
    violet: 'bg-violet-50/80 dark:bg-violet-950/30',
    sky: 'bg-sky-50/80 dark:bg-sky-950/30',
    emerald: 'bg-emerald-50/80 dark:bg-emerald-950/30',
    rose: 'bg-rose-50/80 dark:bg-rose-950/30',
    amber: 'bg-amber-50/80 dark:bg-amber-950/30',
    slate: 'bg-slate-50/90 dark:bg-slate-900/50',
};

const hoverBorder = {
    violet: 'hover:border-violet-300/90 dark:hover:border-violet-600/55',
    sky: 'hover:border-sky-300/90 dark:hover:border-sky-600/55',
    emerald: 'hover:border-emerald-300/90 dark:hover:border-emerald-600/55',
    rose: 'hover:border-rose-300/90 dark:hover:border-rose-600/55',
    amber: 'hover:border-amber-300/90 dark:hover:border-amber-600/55',
    slate: 'hover:border-slate-300 dark:hover:border-slate-600',
};
</script>

<template>
  <div
    class="doc-folder-card group relative flex w-full min-w-0 flex-col overflow-hidden rounded-xl border text-left transition duration-150"
    :class="[
      surface[tone] || surface.violet,
      active
        ? 'border-brand/35 shadow-sm ring-2 ring-brand/15'
        : `border-slate-200/80 ${hoverBorder[tone] || hoverBorder.violet} hover:shadow-md dark:border-slate-700`,
      dropActive ? 'border-brand ring-2 ring-brand/25 bg-brand/[0.04]' : '',
      selected ? 'ring-2 ring-brand/20 border-brand/30' : '',
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
    <label
      v-if="selectable"
      class="absolute left-2 top-2 z-10 grid h-5 w-5 cursor-pointer place-items-center rounded opacity-0 transition group-hover:opacity-100 focus-within:opacity-100"
      :class="selected ? '!opacity-100' : ''"
      @click.stop
    >
      <input
        type="checkbox"
        class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
        :checked="selected"
        :aria-label="`Chọn ${name}`"
        @change="emit('toggle-select')"
      >
    </label>

    <button
      type="button"
      class="flex min-w-0 flex-1 items-start gap-3 px-3 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand/30"
      @click="emit('click')"
    >
      <span class="relative grid h-11 w-11 shrink-0 place-items-center">
        <span
          class="absolute inset-0 rounded-xl shadow-sm ring-1"
          :class="chipBg[tone] || chipBg.violet"
        />
        <AppIcon
          :name="icon || 'folder'"
          :size="22"
          class="relative"
          :class="chipIcon[tone] || chipIcon.violet"
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
          <template v-else>
            <span class="inline-flex items-center gap-1 rounded-md bg-white/80 px-1.5 py-0.5 tabular-nums ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700">
              <AppIcon
                name="documents"
                :size="10"
                class="text-sky-500"
              />
              {{ fileCount }}
            </span>
            <span
              v-if="subfolderCount > 0"
              class="inline-flex items-center gap-1 rounded-md bg-white/80 px-1.5 py-0.5 tabular-nums ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700"
            >
              <AppIcon
                name="folder"
                :size="10"
                class="text-violet-500"
              />
              {{ subfolderCount }}
            </span>
          </template>
        </span>
      </span>
    </button>
  </div>
</template>
