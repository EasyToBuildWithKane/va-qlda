<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
});

// Dot/accent colour per event tone (matches the server DailyReportTimeline map).
const toneClasses = {
    slate: 'bg-slate-400',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
};

const iconFor = {
    created: 'report-today',
    submitted: 'clock',
    recalled: 'back',
    rejected: 'refresh',
    reviewed: 'done',
    addendum_created: 'plus',
    deleted: 'delete',
};

const items = computed(() => props.events ?? []);

const iconOf = (event) => iconFor[event] ?? 'info';

const formatAt = (iso) =>
    iso ? new Date(iso).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
</script>

<template>
  <div class="card p-5">
    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
      <AppIcon
        name="report-history"
        :size="13"
      />
      Lịch sử báo cáo
    </p>

    <div
      v-if="items.length"
      class="relative mt-4 space-y-4 pl-8"
    >
      <span class="absolute bottom-3 left-[11px] top-3 w-px bg-slate-200" />

      <div
        v-for="(ev, i) in items"
        :key="`${ev.event}-${i}`"
        class="relative"
      >
        <span
          class="absolute -left-8 top-0.5 flex h-6 w-6 items-center justify-center rounded-full text-white shadow ring-4 ring-white"
          :class="toneClasses[ev.tone] ?? toneClasses.slate"
        >
          <AppIcon
            :name="iconOf(ev.event)"
            :size="13"
            :stroke-width="2.25"
          />
        </span>
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
          <span class="min-w-0 break-words text-sm font-semibold text-slate-700">{{ ev.label }}</span>
          <span
            v-if="ev.grade"
            class="rounded bg-brand/10 px-1.5 py-0.5 text-xs font-semibold text-brand"
          >Xếp loại {{ ev.grade }}<template v-if="ev.total != null"> · {{ Number(ev.total).toFixed(2) }}</template></span>
          <span class="text-xs text-slate-400">{{ formatAt(ev.at) }}</span>
        </div>
        <p
          v-if="ev.actor"
          class="mt-0.5 text-xs text-slate-500"
        >
          {{ ev.actor }}
        </p>
        <p
          v-if="ev.reason"
          class="mt-1 break-words rounded-card border border-amber-100 bg-amber-50/60 px-2.5 py-1.5 text-xs italic text-slate-600"
        >
          “{{ ev.reason }}”
        </p>
      </div>
    </div>

    <div
      v-else
      class="mt-4 flex flex-col items-center gap-2 py-6 text-center"
    >
      <AppIcon
        name="report-history"
        :size="28"
        class="text-slate-300"
      />
      <p class="text-sm text-slate-400">
        Chưa có sự kiện nào.
      </p>
    </div>
  </div>
</template>
