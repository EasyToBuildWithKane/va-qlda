<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import { formatVnd } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    metrics: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    activeStatus: { type: String, default: 'all' },
});

const emit = defineEmits(['filter-status']);

const activeKey = computed(() => {
    const map = {
        all: 'proposals',
        pending: 'pending',
        approved: 'approved',
        paid: 'paid',
    };
    return map[props.activeStatus] ?? '';
});

const cards = computed(() => {
    const m = props.metrics ?? {};
    const total = m.proposals_total ?? 0;
    const pending = m.proposals_pending ?? 0;
    const approved = m.proposals_approved ?? 0;

    return [
        {
            key: 'proposals',
            label: 'Phiếu đề xuất',
            value: total,
            tone: 'brand',
            icon: 'documents',
            sub: `Chờ: ${pending} · Đã duyệt: ${approved}`,
            interactive: true,
            payload: 'all',
        },
        {
            key: 'pending',
            label: 'Chờ duyệt PĐX',
            value: pending,
            tone: 'amber',
            icon: 'clock',
            sub: total ? `${Math.round((pending / total) * 100)}% phiếu` : 'Bấm để lọc',
            progress: total ? Math.round((pending / total) * 100) : null,
            interactive: true,
            payload: 'pending',
        },
        {
            key: 'approved',
            label: 'ĐNTT chờ duyệt',
            value: m.payment_requests_pending ?? 0,
            tone: 'violet',
            icon: 'review-reports',
            sub: `Đã duyệt: ${m.payment_requests_approved ?? 0} · Đã TT: ${m.payment_requests_paid ?? 0}`,
            interactive: false,
        },
        {
            key: 'paid',
            label: 'Đã thanh toán',
            value: formatVnd(m.budget_paid_total),
            tone: 'emerald',
            icon: 'budget',
            sub: `NS đề xuất: ${formatVnd(m.budget_proposed_total)}`,
            interactive: false,
            isVnd: true,
            rawPaid: m.budget_paid_total,
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('filter-status', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê quy trình mua AI"
    heading="Tổng quan PĐX & thanh toán"
    hint="Thẻ có viền nét đứt — bấm để lọc danh sách đề xuất"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 xl:grid-cols-4"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="metrics?.proposals_total ?? 0"
    :loading="loading"
    @select="onSelect"
  >
    <template #value="{ card }">
      <template v-if="card.isVnd">
        <VndAmount
          :amount="card.rawPaid ?? 0"
          compact
          class="font-display text-xl font-bold text-slate-900 sm:text-[1.65rem]"
        />
      </template>
      <template v-else>
        {{ card.value }}
      </template>
    </template>
  </KpiSummaryStrip>
</template>
