import { currency } from '@/composables/useFormat';

/** Parse backend `Y-m-d H:i` / ISO strings reliably. */
export function parseWorkflowAt(value) {
    if (!value) return null;
    const raw = String(value).trim();
    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const d = new Date(normalized);
    return Number.isNaN(d.getTime()) ? null : d;
}

/** Hiển thị ngày giờ; chỉ ngày khi 00:00 (ngày thanh toán không giờ). */
export function formatWorkflowAt(value) {
    const d = parseWorkflowAt(value);
    if (!d) return '—';
    const isMidnight = d.getHours() === 0 && d.getMinutes() === 0;
    if (isMidnight) {
        return d.toLocaleDateString('vi-VN');
    }
    return (
        d.toLocaleDateString('vi-VN')
        + ' '
        + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
    );
}

export function sortTimelineAscending(events) {
    if (!events?.length) return [];
    return [...events].sort((a, b) => {
        const ta = parseWorkflowAt(a.at)?.getTime() ?? 0;
        const tb = parseWorkflowAt(b.at)?.getTime() ?? 0;
        return ta - tb;
    });
}

export function timelineEventIcon(eventId) {
    switch (eventId) {
        case 'pdx-created':
        case 'dntt-created':
            return 'add';
        case 'pdx-approved':
        case 'dntt-approved':
            return 'check';
        case 'pdx-rejected':
        case 'dntt-rejected':
            return 'close';
        case 'dntt-paid':
            return 'banknote';
        default:
            return 'report-history';
    }
}

export function timelinePhaseStyles(phase) {
    switch (phase) {
        case 'pdx':
            return {
                ring: 'ring-brand/25',
                dot: 'bg-brand',
                icon: 'text-brand',
                chip: 'bg-brand/8 text-brand border-brand/15',
            };
        case 'dntt':
            return {
                ring: 'ring-emerald-500/25',
                dot: 'bg-emerald-600',
                icon: 'text-emerald-700',
                chip: 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
            };
        case 'payment':
            return {
                ring: 'ring-sky-500/25',
                dot: 'bg-sky-600',
                icon: 'text-sky-700',
                chip: 'bg-sky-50 text-sky-800 border-sky-200/80',
            };
        default:
            return {
                ring: 'ring-slate-200',
                dot: 'bg-slate-400',
                icon: 'text-slate-500',
                chip: 'bg-slate-50 text-slate-600 border-slate-200',
            };
    }
}

export function formatTimelineDetail(ev) {
    if (!ev?.detail) return '';
    if (ev.id === 'dntt-paid' && ev.detail.includes('VNĐ')) {
        const num = ev.detail.replace(/[^\d]/g, '');
        if (num) return currency(Number(num));
    }
    return ev.detail;
}
