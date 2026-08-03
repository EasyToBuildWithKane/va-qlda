<script setup>
import { computed } from 'vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
});

const submittedCount = computed(() => props.roles.filter((r) => r.status === 'submitted').length);
const total = computed(() => props.roles.length);
const pct = computed(() => (total.value > 0 ? Math.round((submittedCount.value / total.value) * 100) : 0));
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center justify-between text-[11px] text-slate-500">
      <span>Tiến độ hội đồng</span>
      <span class="tabular-nums">{{ submittedCount }}/{{ total }} · {{ pct }}%</span>
    </div>
    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
      <div
        class="h-full rounded-full bg-brand transition-all"
        :style="{ width: `${pct}%` }"
      />
    </div>
    <div class="flex flex-wrap gap-1">
      <span
        v-for="role in roles"
        :key="role.role_key"
        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
        :class="role.status === 'submitted'
          ? 'bg-emerald-50 text-emerald-700'
          : role.status === 'draft'
            ? 'bg-amber-50 text-amber-700'
            : 'bg-slate-100 text-slate-500'"
        :title="role.label"
      >
        {{ role.label }}
      </span>
    </div>
  </div>
</template>
