<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

const props = defineProps({
    counts: { type: Object, required: true },
    cards: { type: Object, default: null },
    activeKpi: { type: String, default: null },
    filtered: { type: Boolean, default: false },
});

const emit = defineEmits(['filter-status']);

const monthly = computed(() => props.cards?.monthly_cost_running ?? props.cards?.monthly_cost_active ?? 0);
const yearly = computed(() => monthly.value * 12);

const statusItems = computed(() => {
    const pc = props.counts ?? {};
    return [
        {
            key: 'total',
            label: 'Tổng',
            hint: 'phiếu',
            value: pc.total ?? 0,
            icon: 'task',
            tone: 'text-slate-800',
            chip: 'hover:bg-slate-50',
            active: 'ring-slate-300 bg-slate-50',
        },
        {
            key: 'pending',
            label: 'Chờ duyệt',
            hint: pc.pending > 0 ? 'cần xử lý' : '—',
            value: pc.pending ?? 0,
            icon: 'flag',
            tone: 'text-amber-800',
            chip: 'hover:bg-amber-50/80',
            active: 'ring-amber-300 bg-amber-50',
            highlight: (pc.pending ?? 0) > 0,
        },
        {
            key: 'approved',
            label: 'Đã duyệt',
            hint: 'tính chi phí',
            value: pc.approved ?? 0,
            icon: 'done',
            tone: 'text-emerald-800',
            chip: 'hover:bg-emerald-50/80',
            active: 'ring-emerald-300 bg-emerald-50',
        },
        {
            key: 'rejected',
            label: 'Từ chối',
            hint: '—',
            value: pc.rejected ?? 0,
            icon: 'close',
            tone: 'text-rose-800',
            chip: 'hover:bg-rose-50/80',
            active: 'ring-rose-300 bg-rose-50',
        },
        {
            key: 'purchased',
            label: 'Đã mua',
            hint: '—',
            value: pc.purchased ?? 0,
            icon: 'money',
            tone: 'text-violet-800',
            chip: 'hover:bg-violet-50/80',
            active: 'ring-violet-300 bg-violet-50',
        },
        {
            key: 'active',
            label: 'Đang dùng',
            hint: '—',
            value: pc.active ?? 0,
            icon: 'account',
            tone: 'text-teal-800',
            chip: 'hover:bg-teal-50/80',
            active: 'ring-teal-300 bg-teal-50',
        },
    ];
});

function onStatusClick(item) {
    emit('filter-status', item.key);
}

function isActive(key) {
    if (key === 'total') return props.activeKpi === null || props.activeKpi === 'total';
    return props.activeKpi === key;
}
</script>

<template>
  <section
    class="mb-5 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm"
    aria-label="Tổng quan phiếu và chi phí"
  >
    <!-- Một hàng trên desktop; mobile: status scroll + cost hàng dưới -->
    <div class="flex flex-col xl:flex-row xl:items-stretch">
      <div class="flex min-w-0 flex-1 flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-3 sm:p-3.5">
        <div class="flex shrink-0 items-center gap-2.5 sm:w-[7.5rem] sm:flex-col sm:items-start sm:gap-0.5 sm:border-r sm:border-slate-100 sm:pr-3">
          <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
              Trạng thái phiếu
            </h3>
            <p class="text-[10px] text-slate-400">
              {{ filtered ? 'Theo bộ lọc' : 'Bấm để lọc bảng' }}
            </p>
          </div>
        </div>

        <div class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto pb-0.5 sm:pb-0 [-ms-overflow-style:none] [scrollbar-width:thin]">
          <button
            v-for="item in statusItems"
            :key="item.key"
            type="button"
            class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-100 px-2.5 py-2 text-left transition"
            :class="[
              item.chip,
              item.highlight ? 'border-amber-200/90' : '',
              isActive(item.key) ? `ring-2 ring-offset-1 ${item.active}` : 'bg-white',
            ]"
            :title="`${item.label}: ${item.value} · ${item.hint}`"
            @click="onStatusClick(item)"
          >
            <span
              class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-50"
              :class="isActive(item.key) ? '' : ''"
            >
              <AppIcon
                :name="item.icon"
                :size="16"
                :class="item.tone"
              />
            </span>
            <span class="min-w-[4.25rem]">
              <span class="block text-[10px] font-medium leading-tight text-slate-500">{{ item.label }}</span>
              <span
                class="font-display text-lg font-bold tabular-nums leading-tight"
                :class="item.tone"
              >
                {{ item.value }}
              </span>
              <span class="block text-[9px] leading-tight text-slate-400">{{ item.hint }}</span>
            </span>
          </button>
        </div>
      </div>

      <div
        class="grid shrink-0 grid-cols-2 divide-x divide-slate-100 border-t border-slate-100 bg-slate-50/40 xl:w-[min(22rem,32%)] xl:border-t-0 xl:border-l"
      >
        <div class="flex flex-col justify-center px-3.5 py-3 sm:px-4">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            Chi phí / tháng
          </p>
          <p class="text-[10px] leading-snug text-slate-400">
            Phiếu đã duyệt · đang phát sinh
          </p>
          <VndAmount
            :amount="monthly"
            compact
            class="mt-1.5 font-display text-base font-bold text-brand sm:text-lg"
          />
        </div>
        <div class="flex flex-col justify-center px-3.5 py-3 sm:px-4">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            Ước tính / năm
          </p>
          <p class="text-[10px] leading-snug text-slate-400">
            × 12 tháng
          </p>
          <VndAmount
            :amount="yearly"
            compact
            class="mt-1.5 font-display text-base font-bold text-indigo-700 sm:text-lg"
          />
        </div>
      </div>
    </div>
  </section>
</template>
