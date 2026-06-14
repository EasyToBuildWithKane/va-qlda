<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { hours as fmtHours } from '@/composables/useFormat';
import CoachingSessionCard from '@/modules/coaching/components/CoachingSessionCard.vue';

defineProps({
    groups: { type: Array, required: true },
    statusOptions: { type: Array, default: () => [] },
    statusUpdatingIds: { type: Set, required: true },
    isGroupExpanded: { type: Function, required: true },
});

const emit = defineEmits(['toggle-group', 'update-status', 'detail', 'delete']);
</script>

<template>
  <div
    class="flex min-h-0 flex-col divide-y divide-slate-100"
    :class="groups.length === 1 ? 'min-h-0 flex-1' : ''"
  >
    <section
      v-for="group in groups"
      :key="group.key"
      class="flex min-h-0 flex-col"
      :class="groups.length === 1 ? 'flex-1' : ''"
    >
      <button
        type="button"
        class="flex w-full items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5 text-left transition hover:bg-slate-100/80 sm:px-5"
        @click="emit('toggle-group', group.key)"
      >
        <AppIcon
          name="chevron-down"
          :size="16"
          class="shrink-0 text-slate-500 transition-transform"
          :class="isGroupExpanded(group.key) ? '' : '-rotate-90'"
        />
        <div class="min-w-0 flex-1">
          <Link
            v-if="group.course"
            :href="route('coaching.courses.show', { course: group.course.id })"
            class="block truncate text-sm font-semibold text-slate-800 hover:text-brand"
            @click.stop
          >
            <span class="font-mono text-xs font-normal text-slate-500">{{ group.course.code }}</span>
            <span class="ml-2">{{ group.course.name }}</span>
          </Link>
          <span
            v-else
            class="text-sm font-semibold text-slate-600"
          >{{ group.label }}</span>
          <p class="mt-0.5 text-[11px] text-slate-500">
            {{ group.stats.completed }}/{{ group.stats.total }} hoàn thành
            <span class="mx-1 text-slate-300">·</span>
            {{ fmtHours(group.stats.hours) }} giờ
          </p>
        </div>
        <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200">
          {{ group.items.length }} buổi
        </span>
      </button>

      <div
        v-if="isGroupExpanded(group.key)"
        class="grid grid-cols-1 gap-3 px-4 py-4 sm:grid-cols-2 sm:px-5 xl:grid-cols-3 2xl:grid-cols-4"
        :class="groups.length === 1 ? 'min-h-0 flex-1 auto-rows-fr' : ''"
      >
        <CoachingSessionCard
          v-for="s in group.items"
          :key="s.id"
          :session="s"
          :status-options="statusOptions"
          :status-updating="statusUpdatingIds.has(s.id)"
          class="h-full min-h-0"
          @update-status="(sess, val) => emit('update-status', sess, val)"
          @detail="emit('detail', $event)"
          @delete="emit('delete', $event)"
        />
      </div>
    </section>
  </div>
</template>
