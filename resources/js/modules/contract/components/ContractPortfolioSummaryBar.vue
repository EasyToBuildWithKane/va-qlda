<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
    activePayment: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activePayment === 'unpaid') return 'unpaid';
    if (props.activeStatus === 'active') return 'active';
    if (props.activeStatus === 'expiring_soon') return 'expiring_soon';
    if (props.activeStatus === 'expired') return 'expired';
    if (!props.activeStatus && !props.activePayment) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng hợp đồng',
            value: total,
            tone: 'brand',
            icon: 'documents',
            sub: total ? 'Toàn danh mục' : 'Chưa có hợp đồng',
            interactive: true,
            payload: { status: '', payment_status: '' },
        },
        {
            key: 'active',
            label: 'Đang hiệu lực',
            value: s.active ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.active ?? 0)}% còn hiệu lực` : 'Bấm để lọc',
            progress: pct(s.active ?? 0),
            interactive: true,
            payload: { status: 'active', payment_status: '' },
        },
        {
            key: 'expiring_soon',
            label: 'Sắp hết hạn',
            value: s.expiring_soon ?? 0,
            tone: 'amber',
            icon: 'clock',
            sub: total ? 'Trong cửa sổ nhắc gia hạn' : 'Bấm để lọc',
            progress: pct(s.expiring_soon ?? 0),
            interactive: true,
            payload: { status: 'expiring_soon', payment_status: '' },
        },
        {
            key: 'expired',
            label: 'Đã hết hạn',
            value: s.expired ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: total ? `${pct(s.expired ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.expired ?? 0),
            interactive: true,
            payload: { status: 'expired', payment_status: '' },
        },
        {
            key: 'unpaid',
            label: 'Chưa thanh toán',
            value: s.unpaid ?? 0,
            tone: 'sky',
            icon: 'budget',
            sub: 'Hợp đồng còn hiệu lực',
            interactive: true,
            payload: { status: '', payment_status: 'unpaid' },
        },
        {
            key: 'annual_cost',
            label: 'Chi phí năm',
            value: formatMoneyShort(s.annual_cost ?? 0),
            tone: 'violet',
            icon: 'money',
            sub: 'Tổng annual_cost toàn danh mục',
            interactive: false,
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê danh mục hợp đồng"
    heading="Tổng quan danh mục hợp đồng"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    grid-class="grid-cols-2 sm:grid-cols-3 lg:grid-cols-6"
    @select="onSelect"
  />
</template>
