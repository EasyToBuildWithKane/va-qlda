<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    /** Currently focused date (YYYY-MM-DD). */
    selected: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const WEEKDAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

function parse(ymd) {
    if (!ymd) return new Date();
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, (m || 1) - 1, d || 1);
}

function toYmd(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const cursor = ref(parse(props.selected));

watch(
    () => props.selected,
    (v) => {
        if (v) cursor.value = parse(v);
    },
);

const monthLabel = computed(() =>
    cursor.value.toLocaleDateString('vi-VN', { month: 'long', year: 'numeric' }),
);

const todayYmd = toYmd(new Date());

const days = computed(() => {
    const year = cursor.value.getFullYear();
    const month = cursor.value.getMonth();
    const first = new Date(year, month, 1);
    // Monday-first offset (JS getDay: 0=Sun).
    const lead = (first.getDay() + 6) % 7;
    const startDate = new Date(year, month, 1 - lead);

    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + i);
        const ymd = toYmd(d);
        return {
            ymd,
            label: d.getDate(),
            inMonth: d.getMonth() === month,
            isToday: ymd === todayYmd,
            isSelected: ymd === props.selected,
        };
    });
});

function shift(delta) {
    cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + delta, 1);
}
</script>

<template>
  <div class="select-none">
    <div class="mb-2 flex items-center justify-between">
      <span class="text-sm font-semibold capitalize text-slate-700">{{ monthLabel }}</span>
      <div class="flex items-center gap-0.5">
        <button
          type="button"
          class="grid h-6 w-6 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Tháng trước"
          @click="shift(-1)"
        >
          <AppIcon
            name="chevron-left"
            :size="15"
          />
        </button>
        <button
          type="button"
          class="grid h-6 w-6 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Tháng sau"
          @click="shift(1)"
        >
          <AppIcon
            name="chevron-right"
            :size="15"
          />
        </button>
      </div>
    </div>

    <div class="grid grid-cols-7 gap-0.5 text-center">
      <span
        v-for="w in WEEKDAYS"
        :key="w"
        class="py-1 text-[10px] font-medium uppercase tracking-wide text-slate-400"
      >{{ w }}</span>
      <button
        v-for="d in days"
        :key="d.ymd"
        type="button"
        class="relative grid aspect-square place-items-center rounded-lg text-xs transition"
        :class="[
          d.inMonth ? 'text-slate-700' : 'text-slate-300',
          d.isSelected
            ? 'bg-brand font-semibold text-white shadow-sm'
            : d.isToday
              ? 'bg-brand/10 font-semibold text-brand'
              : 'hover:bg-slate-100',
        ]"
        @click="emit('select', d.ymd)"
      >
        {{ d.label }}
      </button>
    </div>
  </div>
</template>
