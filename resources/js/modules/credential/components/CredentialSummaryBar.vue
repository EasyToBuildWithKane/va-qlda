<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeKpi: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => props.activeKpi || '');

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    const card = (key, label, value, tone, icon, sub, filterKey, progress = null) => ({
        key,
        label,
        value,
        tone,
        icon,
        sub,
        interactive: filterKey !== undefined,
        payload: filterKey === null ? { kpi: '' } : filterKey ? { kpi: filterKey } : null,
        progress,
    });

    return [
        card('total', 'Tổng tài khoản', total, 'brand', 'vault', total ? 'Toàn hệ thống' : 'Chưa có bản ghi', null),
        card('active', 'Đang hoạt động', s.active ?? 0, 'emerald', 'done', `${pct(s.active ?? 0)}% tổng`, 'active', pct(s.active ?? 0)),
        card('shared', 'Dùng chung', s.shared ?? 0, 'sky', 'people', 'Tài khoản shared', 'shared'),
        card('personal', 'Cá nhân', s.personal ?? 0, 'violet', 'account', 'Không shared', 'personal'),
        card('vps', 'VPS', s.vps_count ?? 0, 'sky', 'globe', 'Đang quản lý', 'vps'),
        card('database', 'Database', s.database_count ?? 0, 'violet', 'documents', 'Đang quản lý', 'database'),
    ];
});

function onSelect(card) {
    if (card.payload && 'kpi' in card.payload) {
        emit('quick-filter', card.payload);
    }
}
</script>

<template>
  <KpiSummaryStrip
    :cards="cards"
    :active-key="activeKey === '' && !activeKpi ? 'total' : activeKey"
    aria-label="Thống kê tài khoản và mật khẩu"
    heading="Tài khoản & tài sản số"
    hint="Thẻ viền nét đứt — bấm lọc nhanh"
    :progress-denominator="summary.total ?? 0"
    grid-class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    @select="onSelect"
  />
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
