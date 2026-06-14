<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import MiniCalendar from './MiniCalendar.vue';
import { statusMeta } from '../composables/useCoachingCalendar.js';

const props = defineProps({
    coaches: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    selectedDate: { type: String, default: '' },
});

const emit = defineEmits(['select-date', 'toggle-coach', 'toggle-status', 'clear']);

const hasActiveFilters = computed(
    () => props.filters.coaches.size > 0 || props.filters.statuses.size > 0 || !!props.filters.query,
);

function coachActive(name) {
    return props.filters.coaches.size === 0 || props.filters.coaches.has(name);
}
</script>

<template>
  <div class="flex h-full flex-col gap-5 overflow-y-auto p-4">
    <MiniCalendar
      :selected="selectedDate"
      @select="(d) => emit('select-date', d)"
    />

    <!-- Status legend / filter -->
    <section>
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

    <!-- Coaches -->
    <section class="min-h-0 flex-1">
      <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Coach
      </h3>
      <ul class="space-y-0.5">
        <li
          v-for="coach in coaches"
          :key="coach.name"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-slate-50"
            :class="coachActive(coach.name) ? '' : 'opacity-40'"
            @click="emit('toggle-coach', coach.name)"
          >
            <Avatar
              :name="coach.name"
              :size="28"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-slate-700">{{ coach.name }}</span>
              <span class="block text-[11px] text-slate-400">{{ coach.courses_count }} khóa</span>
            </span>
            <span
              class="grid h-4 w-4 place-items-center rounded border"
              :class="
                filters.coaches.has(coach.name)
                  ? 'border-brand bg-brand text-white'
                  : 'border-slate-300 bg-white text-transparent'
              "
            >
              <AppIcon
                name="check"
                :size="12"
              />
            </span>
          </button>
        </li>
        <li
          v-if="!coaches.length"
          class="px-2 py-2 text-xs text-slate-400"
        >
          Chưa có coach nào.
        </li>
      </ul>
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
