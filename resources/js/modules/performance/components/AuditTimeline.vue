<script setup>
import WeeklyAuditCard from './WeeklyAuditCard.vue';

defineProps({
    weeks: { type: Array, default: () => [] },
});
</script>

<template>
  <div
    v-if="weeks.length"
    class="relative space-y-4 pl-6"
  >
    <!-- Timeline rail -->
    <span class="absolute bottom-2 left-[7px] top-2 w-px bg-slate-200" />

    <div
      v-for="week in weeks"
      :key="week.key"
      class="relative"
    >
      <span class="absolute -left-6 top-5 h-3.5 w-3.5 rounded-full border-2 border-white bg-brand shadow" />
      <WeeklyAuditCard :week="week" />
    </div>
  </div>
  <p
    v-else
    class="py-10 text-center text-sm text-slate-400"
  >
    Không có dữ liệu audit trong khoảng thời gian này.
  </p>
</template>
