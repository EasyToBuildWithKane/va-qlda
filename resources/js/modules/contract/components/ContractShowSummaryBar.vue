<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import {
    expiryLabel,
    expiryTone,
    formatDate,
    formatMoneyShort,
} from '@/modules/contract/composables/useContractFormat.js';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    contract: { type: Object, required: true },
    annualLabel: { type: String, default: '' },
    attachmentCount: { type: Number, default: 0 },
    addendaCount: { type: Number, default: 0 },
    avgReviewScore: { type: Number, default: null },
});

const emit = defineEmits(['navigate-tab']);

const expiryCardTone = computed(() => {
    const map = {
        rose: 'rose',
        amber: 'amber',
        sky: 'sky',
        emerald: 'emerald',
        slate: 'slate',
    };
    return map[expiryTone(props.contract?.days_until_expiry)] ?? 'slate';
});

const cards = computed(() => {
    const c = props.contract;
    const statusLabel = c?.status?.label ?? EMPTY_LABELS.notUpdated;
    const paymentSub = c?.payment_status?.label
        ? `Thanh toán: ${c.payment_status.label}`
        : 'Chưa có trạng thái thanh toán';

    const annualAmt = c?.annual_cost ?? c?.annual_cost_resolved;
    const annualValue = props.annualLabel
        || (annualAmt != null ? formatMoneyShort(annualAmt, c?.currency ?? 'VND') : EMPTY_LABELS.notUpdated);
    const annualIsText = annualAmt == null && !props.annualLabel;

    const healthVal = c?.health_score;
    const healthDisplay = healthVal != null ? healthVal : EMPTY_LABELS.notUpdated;
    const healthSub = props.avgReviewScore != null
        ? `Đánh giá NCC ★ ${props.avgReviewScore}`
        : 'Chưa có đánh giá NCC';

    const expiryDisplay = c?.expiry_date
        ? expiryLabel(c.days_until_expiry)
        : EMPTY_LABELS.notUpdated;
    const expirySub = c?.expiry_date
        ? formatDate(c.expiry_date)
        : 'Chưa chọn ngày hết hạn';

    return [
        {
            key: 'status',
            label: 'Trạng thái',
            value: statusLabel,
            valueKind: 'text',
            tone: 'brand',
            icon: 'documents',
            sub: paymentSub,
            interactive: false,
        },
        {
            key: 'annual',
            label: 'Chi phí năm',
            value: annualValue,
            valueKind: annualIsText ? 'text' : undefined,
            tone: 'brand',
            icon: 'money',
            sub: c?.currency ? `Tiền tệ ${c.currency}` : 'Chi phí duy trì hàng năm',
            interactive: false,
        },
        {
            key: 'health',
            label: 'Sức khỏe HĐ',
            value: healthDisplay,
            valueKind: healthVal == null ? 'text' : undefined,
            suffix: healthVal != null ? '/100' : undefined,
            tone: 'amber',
            icon: 'star',
            sub: healthSub,
            interactive: false,
        },
        {
            key: 'expiry',
            label: 'Hết hạn',
            value: expiryDisplay,
            valueKind: c?.expiry_date ? undefined : 'text',
            tone: expiryCardTone.value,
            icon: 'renewal',
            sub: expirySub,
            interactive: false,
        },
        {
            key: 'documents',
            label: 'Hồ sơ đính kèm',
            value: props.attachmentCount,
            tone: 'sky',
            icon: 'documents',
            sub: props.attachmentCount ? 'Bấm để mở tab Hồ sơ' : 'Chưa có tệp',
            interactive: true,
            payload: 'documents',
        },
        {
            key: 'renewals',
            label: 'Phụ lục gia hạn',
            value: props.addendaCount,
            tone: 'violet',
            icon: 'renewal',
            sub: props.addendaCount ? 'Bấm để xem lịch sử' : 'Chưa có phụ lục',
            interactive: true,
            payload: 'renewals',
        },
    ];
});

function onSelect(card) {
    if (card.interactive && card.payload) {
        emit('navigate-tab', card.payload);
    }
}
</script>

<template>
  <KpiSummaryStrip
    variant="embedded"
    aria-label="Thống kê tổng quan hợp đồng"
    heading="Chỉ số hợp đồng"
    hint="Thẻ viền nét đứt — bấm để mở tab liên quan"
    :cards="cards"
    active-key=""
    grid-class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    @select="onSelect"
  />
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
