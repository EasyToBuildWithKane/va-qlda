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

const statusItems = computed(() => {
    const pc = props.counts ?? {};
    return [
        {
            key: 'total',
            label: 'Tổng',
            hint: 'phiếu',
            value: pc.total ?? 0,
            icon: 'task',
            tone: 'text-slate-700',
            iconBg: 'bg-slate-100',
            ring: 'ring-slate-200',
        },
        {
            key: 'pending',
            label: 'Chờ duyệt',
            hint: pc.pending > 0 ? 'cần xử lý' : '—',
            value: pc.pending ?? 0,
            icon: 'flag',
            tone: 'text-amber-700',
            iconBg: 'bg-amber-50',
            ring: 'ring-amber-200',
            highlight: (pc.pending ?? 0) > 0,
        },
        {
            key: 'approved',
            label: 'Đã duyệt',
            hint: 'tính chi phí',
            value: pc.approved ?? 0,
            icon: 'done',
            tone: 'text-emerald-700',
            iconBg: 'bg-emerald-50',
            ring: 'ring-emerald-200',
        },
        {
            key: 'rejected',
            label: 'Từ chối',
            hint: '—',
            value: pc.rejected ?? 0,
            icon: 'close',
            tone: 'text-rose-700',
            iconBg: 'bg-rose-50',
            ring: 'ring-rose-200',
        },
        {
            key: 'purchased',
            label: 'Đã mua',
            hint: '—',
            value: pc.purchased ?? 0,
            icon: 'money',
            tone: 'text-violet-700',
            iconBg: 'bg-violet-50',
            ring: 'ring-violet-200',
        },
        {
            key: 'active',
            label: 'Đang dùng',
            hint: '—',
            value: pc.active ?? 0,
            icon: 'account',
            tone: 'text-teal-700',
            iconBg: 'bg-teal-50',
            ring: 'ring-teal-200',
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
  <section class="mb-5 space-y-3">
    <!-- Trạng thái phiếu — thẻ nhỏ, wrap -->
    <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-3.5 py-2">
        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">
          Trạng thái phiếu
        </h3>
        <span
          v-if="filtered"
          class="text-[10px] text-slate-400"
        >Số liệu theo bộ lọc</span>
        <span
          v-else
          class="text-[10px] text-slate-400"
        >Bấm để lọc bảng</span>
      </div>
      <div class="flex flex-wrap gap-2 p-3">
        <button
          v-for="item in statusItems"
          :key="item.key"
          type="button"
          class="flex w-[5.75rem] flex-col items-center rounded-xl border border-slate-100 bg-white px-2 py-2.5 text-center transition hover:border-slate-200 hover:shadow-sm"
          :class="[
            item.highlight ? 'border-amber-200 bg-amber-50/40' : '',
            isActive(item.key) ? `ring-2 ${item.ring} border-transparent` : '',
          ]"
          :title="`${item.label}: ${item.value}`"
          @click="onStatusClick(item)"
        >
          <span
            class="mb-1.5 grid h-7 w-7 place-items-center rounded-lg"
            :class="item.iconBg"
          >
            <AppIcon
              :name="item.icon"
              :size="15"
              :class="item.tone"
            />
          </span>
          <span class="text-[10px] font-medium leading-tight text-slate-500">{{ item.label }}</span>
          <span
            class="font-display text-xl font-bold tabular-nums leading-none"
            :class="item.tone"
          >
            {{ item.value }}
          </span>
          <span class="mt-1 text-[9px] leading-tight text-slate-400">{{ item.hint }}</span>
        </button>
      </div>
    </div>

    <!-- Ngân sách — 2 thẻ rộng vừa phải -->
    <div class="grid gap-2.5 sm:grid-cols-2 lg:max-w-2xl">
      <div class="flex items-start justify-between gap-3 rounded-xl border border-brand/15 bg-gradient-to-br from-brand-50/80 to-white px-3.5 py-3">
        <div class="min-w-0">
          <p class="text-xs font-semibold text-slate-700">
            Chi phí / tháng
          </p>
          <p class="mt-0.5 text-[10px] leading-snug text-slate-500">
            Phiếu đã duyệt · đang phát sinh
          </p>
        </div>
        <VndAmount
          :amount="monthly"
          compact
          class="shrink-0 text-right font-display text-sm font-bold text-brand"
        />
      </div>
      <div class="flex items-start justify-between gap-3 rounded-xl border border-indigo-200/60 bg-indigo-50/40 px-3.5 py-3">
        <div class="min-w-0">
          <p class="text-xs font-semibold text-slate-700">
            Ước tính / năm
          </p>
          <p class="mt-0.5 text-[10px] leading-snug text-slate-500">
            = Chi phí tháng × 12
          </p>
        </div>
        <VndAmount
          :amount="monthly * 12"
          compact
          class="shrink-0 text-right font-display text-sm font-bold text-indigo-700"
        />
      </div>
    </div>
  </section>
</template>
