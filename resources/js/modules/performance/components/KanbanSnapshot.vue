<script setup>
import { tailwindToHex } from '../composables/useChartTheme.js';

defineProps({
    columns: { type: Array, default: () => [] },
});
</script>

<template>
  <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
    <div
      v-for="col in columns"
      :key="col.status"
      class="rounded-lg border border-slate-200 bg-slate-50/60 p-2"
    >
      <div class="mb-1.5 flex items-center justify-between">
        <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
          <span
            class="h-2 w-2 rounded-full"
            :style="{ backgroundColor: tailwindToHex(col.color) }"
          />
          {{ col.label }}
        </span>
        <span class="text-[11px] font-bold tabular-nums text-slate-400">{{ col.count }}</span>
      </div>
      <ul class="space-y-1">
        <li
          v-for="t in col.tasks"
          :key="t.id"
          class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-600 shadow-sm"
        >
          <p class="truncate">
            {{ t.title }}
          </p>
          <p
            v-if="t.project"
            class="truncate text-[10px] text-slate-400"
          >
            {{ t.project.name }}
          </p>
        </li>
      </ul>
      <p
        v-if="!col.tasks.length"
        class="py-1 text-center text-[10px] text-slate-400"
      >
        Không có task
      </p>
    </div>
  </div>
</template>
