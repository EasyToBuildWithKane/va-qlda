<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import MiniCalendar from './MiniCalendar.vue';
import { statusMeta } from '../composables/useCoachingCalendar.js';

const props = defineProps({
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    selectedDate: { type: String, default: '' },
});

const emit = defineEmits(['select-date', 'toggle-status', 'clear']);

const hasActiveFilters = computed(
    () => props.filters.statuses.size > 0 || !!props.filters.query,
);
</script>

<template>
  <div class="flex h-full flex-col gap-5 overflow-y-auto p-4">
    <MiniCalendar
      :selected="selectedDate"
      @select="(d) => emit('select-date', d)"
    />

    <!-- Status legend / filter -->
    <section class="min-h-0 flex-1">
      <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Trạng thái
      </h3>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="s in statuses"
          :key="s.value"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
          :class="
            filters.statuses.size === 0 || filters.statuses.has(s.value)
              ? 'border-transparent text-white'
              : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'
          "
          :style="
            filters.statuses.size === 0 || filters.statuses.has(s.value)
              ? { backgroundColor: statusMeta(s.value).color }
              : {}
          "
          @click="emit('toggle-status', s.value)"
        >
          <span
            class="h-1.5 w-1.5 rounded-full"
            :style="{
              backgroundColor:
                filters.statuses.size === 0 || filters.statuses.has(s.value)
                  ? '#fff'
                  : statusMeta(s.value).color,
            }"
          />
          {{ s.label }}
        </button>
      </div>
    </section>

    <button
      v-if="hasActiveFilters"
      type="button"
      class="inline-flex items-center justify-center gap-1.5 rounded-btn border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-slate-300"
      @click="emit('clear')"
    >
      <AppIcon
        name="close"
        :size="13"
      />
      Xóa bộ lọc
    </button>
  </div>
</template>
