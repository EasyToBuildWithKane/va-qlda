<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';

/**
 * Workload heatmap dạng thanh ngang theo người — màu theo mức tải (capacity).
 */
const props = defineProps({
    people: { type: Array, default: () => [] },
});

const max = computed(() => Math.max(1, ...props.people.map((p) => p.openTasks)));

const bandColor = {
    healthy: 'bg-emerald-400',
    watch: 'bg-amber-400',
    overloaded: 'bg-rose-500',
};
const bandLabel = {
    healthy: 'Bình thường',
    watch: 'Cần theo dõi',
    overloaded: 'Quá tải',
};
</script>

<template>
  <div
    v-if="people.length"
    class="space-y-2.5"
  >
    <div
      v-for="p in people"
      :key="p.id"
      class="flex items-center gap-3"
    >
      <Avatar
        :name="p.name"
        :src="p.avatar"
        :size="26"
      />
      <div class="min-w-0 flex-1">
        <div class="flex items-center justify-between gap-2">
          <span class="truncate text-[13px] font-medium text-slate-700">{{ p.name }}</span>
          <span class="shrink-0 text-[11px] tabular-nums text-slate-400">{{ p.openTasks }} việc mở</span>
        </div>
        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="bandColor[p.load]"
            :style="{ width: Math.round((p.openTasks / max) * 100) + '%' }"
          />
        </div>
      </div>
      <span
        class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold sm:inline-block"
        :class="{
          'bg-emerald-50 text-emerald-600': p.load === 'healthy',
          'bg-amber-50 text-amber-600': p.load === 'watch',
          'bg-rose-50 text-rose-600': p.load === 'overloaded',
        }"
      >
        {{ bandLabel[p.load] }}
      </span>
    </div>
  </div>
  <p
    v-else
    class="py-6 text-center text-sm text-slate-400"
  >
    Không có dữ liệu khối lượng công việc
  </p>
</template>
