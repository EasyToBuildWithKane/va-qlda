<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatVndCompact } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total_accounts: 0,
            active_accounts: 0,
            expiring_soon: 0,
            expired: 0,
            monthly_cost_all: 0,
        }),
    },
    loading: { type: Boolean, default: false },
});

const cards = computed(() => {
    const s = props.summary ?? {};
    const total = s.total_accounts ?? 0;
    const active = s.active_accounts ?? 0;
    const expiring = s.expiring_soon ?? 0;
    const expired = s.expired ?? 0;
    const monthly = s.monthly_cost_all ?? s.monthly_cost_active ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng tài khoản',
            value: total,
            tone: 'brand',
            icon: 'account',
            sub: total ? 'Trong phạm vi quyền xem' : 'Chưa có tài khoản AI',
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: active,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(active)}% tổng` : 'Chưa có dữ liệu',
            progress: pct(active),
        },
        {
            key: 'expiring',
            label: 'Sắp hết hạn',
            value: expiring,
            tone: 'amber',
            icon: 'clock',
            sub: total ? `${pct(expiring)}% tổng` : 'Chưa có cảnh báo',
            progress: pct(expiring),
        },
        {
            key: 'expired',
            label: 'Hết hạn',
            value: expired,
            tone: 'rose',
            icon: 'alert',
            sub: total ? `${pct(expired)}% tổng` : 'Không có TK hết hạn',
            progress: pct(expired),
        },
        {
            key: 'monthly_cost',
            label: 'Chi phí / tháng',
            value: formatVndCompact(monthly),
            valueKind: 'money',
            tone: 'sky',
            icon: 'budget',
            sub: monthly ? 'Quy đổi theo đơn vị TK' : 'Chưa ghi nhận chi phí',
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê chi phí AI"
    heading="Tổng quan tài khoản & chi phí tháng"
    hint="Số liệu tổng hợp theo nhóm chức năng bên dưới"
    :cards="cards"
    :loading="loading"
    loading-text="Đang tải thống kê chi phí…"
    :progress-denominator="summary?.total_accounts ?? 0"
  />
</template>
