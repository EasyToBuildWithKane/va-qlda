<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';

const props = defineProps({
    data: { type: Object, default: null },
});

const scores = computed(() => [
    { key: 'promotion', label: 'Sẵn sàng thăng tiến', value: props.data?.promotion_score, color: 'emerald' },
    { key: 'retention', label: 'Khả năng giữ chân', value: props.data?.retention_score, color: 'sky' },
    { key: 'risk', label: 'Rủi ro rời đi', value: props.data?.risk_score, color: 'rose' },
]);

const tone = {
    emerald: 'text-emerald-600',
    sky: 'text-sky-600',
    rose: 'text-rose-600',
};
const track = {
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
    rose: 'bg-rose-500',
};
</script>

<template>
  <section
    v-if="data"
    class="rounded-2xl border border-slate-200/70 bg-white shadow-sm"
  >
    <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
      <div class="flex items-center gap-2.5">
        <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="leaderboard"
            :size="16"
          />
        </div>
        <div>
          <h2 class="text-sm font-semibold text-slate-800">
            Kế hoạch kế nhiệm
          </h2>
          <p
            v-if="data.target_role"
            class="text-[12px] text-slate-400"
          >
            Mục tiêu: {{ data.target_role }}
          </p>
        </div>
      </div>
      <Badge
        :label="data.readiness.label"
        :color="data.readiness.color"
      />
    </header>

    <div class="p-5">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div
          v-for="s in scores"
          :key="s.key"
          class="rounded-xl border border-slate-100 p-4 text-center"
        >
          <p
            class="text-2xl font-bold tabular-nums"
            :class="tone[s.color]"
          >
            {{ s.value ?? '—' }}<span
              v-if="s.value != null"
              class="text-sm text-slate-300"
            >/100</span>
          </p>
          <p class="mt-1 text-[12px] text-slate-500">
            {{ s.label }}
          </p>
          <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="track[s.color]"
              :style="{ width: (s.value ?? 0) + '%' }"
            />
          </div>
        </div>
      </div>

      <p
        v-if="data.note"
        class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-[12.5px] text-slate-600"
      >
        {{ data.note }}
      </p>
    </div>
  </section>
</template>
