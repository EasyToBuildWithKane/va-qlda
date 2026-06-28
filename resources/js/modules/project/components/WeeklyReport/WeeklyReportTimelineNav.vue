<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    sprint: { type: Object, default: null },
    weeks: { type: Array, default: () => [] },
    currentWeek: { type: Number, default: 1 },
    activeReportId: { type: [Number, null], default: null },
    pendingWeek: { type: [Number, null], default: null },
});

const emit = defineEmits(['select']);

function fmt(d) {
    if (!d) return '';
    const [, m, day] = d.split('-');
    return `${day}/${m}`;
}

function isDone(status) {
    return status === 'submitted' || status === 'approved';
}
</script>

<template>
  <aside class="flex w-full shrink-0 flex-col gap-3 lg:w-60">
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
      <div class="flex items-center gap-2 px-1 pb-2">
        <AppIcon
          name="sprint"
          :size="15"
          class="text-brand"
        />
        <span class="font-display text-sm font-semibold text-slate-800 dark:text-slate-100">
          {{ sprint?.name || 'Ngoài Sprint' }}
        </span>
      </div>

      <ul class="space-y-1">
        <li
          v-for="w in weeks"
          :key="w.week_number"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm transition"
            :class="(activeReportId && w.report_id === activeReportId) || (pendingWeek === w.week_number)
              ? 'bg-brand/10 text-brand'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
            @click="emit('select', w)"
          >
            <span
              class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold"
              :class="isDone(w.status)
                ? 'bg-emerald-500 text-white'
                : w.report_id
                  ? 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300'
                  : 'bg-slate-200 text-slate-500 dark:bg-slate-700'"
            >
              <AppIcon
                v-if="isDone(w.status)"
                name="check"
                :size="11"
              />
              <template v-else>{{ w.week_number }}</template>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block font-medium">Tuần {{ w.week_number }}</span>
              <span class="block text-[11px] text-slate-400">{{ fmt(w.week_start) }} – {{ fmt(w.week_end) }}</span>
            </span>
            <span
              v-if="w.week_number === currentWeek"
              class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-amber-700 dark:bg-amber-950 dark:text-amber-300"
            >Nay</span>
          </button>
        </li>
      </ul>
    </div>
  </aside>
</template>
