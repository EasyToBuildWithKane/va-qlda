import { computed, unref } from 'vue';

/**
 * Dựng sự kiện FullCalendar cho lịch gia hạn hợp đồng — mỗi hợp đồng là một
 * sự kiện all-day tại `expiry_date`, màu theo độ khẩn (số ngày còn lại).
 * Pure, read-only — không gọi API.
 */
const PALETTE = {
    critical: { bg: '#fee2e2', border: '#f43f5e', text: '#b91c1c' },
    high: { bg: '#fef3c7', border: '#f59e0b', text: '#b45309' },
    medium: { bg: '#e0f2fe', border: '#0ea5e9', text: '#0369a1' },
    safe: { bg: '#dcfce7', border: '#10b981', text: '#047857' },
};

function levelFor(days) {
    if (days === null || days === undefined) return 'safe';
    if (days < 7) return 'critical';
    if (days <= 30) return 'high';
    if (days <= 90) return 'medium';
    return 'safe';
}

/** Inertia / JsonResource đôi khi truyền `{ data: [...] }` hoặc object keyed — không phải Array. */
function normalizeContractList(raw) {
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
}

export function useContractCalendar(contractsRef) {
    const events = computed(() => {
        const contracts = normalizeContractList(unref(contractsRef));
        return contracts
            .filter((c) => c.expiry_date)
            .map((c) => {
                const level = levelFor(c.days_until_expiry);
                const p = PALETTE[level];
                return {
                    id: String(c.id),
                    title: c.name,
                    start: c.expiry_date,
                    allDay: true,
                    backgroundColor: p.bg,
                    borderColor: p.border,
                    textColor: p.text,
                    extendedProps: {
                        contractId: c.id,
                        code: c.code,
                        level,
                        days: c.days_until_expiry,
                        annualCost: c.annual_cost,
                    },
                };
            });
    });

    const legend = [
        { level: 'critical', label: '< 7 ngày', color: PALETTE.critical.border },
        { level: 'high', label: '≤ 30 ngày', color: PALETTE.high.border },
        { level: 'medium', label: '≤ 90 ngày', color: PALETTE.medium.border },
        { level: 'safe', label: '> 90 ngày', color: PALETTE.safe.border },
    ];

    return { events, legend };
}
