import { formatVndCompact } from '@/modules/aiAccount/utils/formatVnd';

export const AI_CHART_BRAND = '#9A0036';
export const AI_CHART_SKY = '#185FA5';
export const AI_CHART_SLATE = '#64748b';
export const AI_CHART_AMBER = '#d97706';
export const AI_CHART_EMERALD = '#059669';
export const AI_CHART_ROSE = '#e11d48';

export const SERIES_STYLES = {
    actual: {
        label: 'Chi phí thực tế',
        color: AI_CHART_BRAND,
        fill: 'rgba(154, 0, 54, 0.1)',
        borderWidth: 2.5,
        fillArea: true,
    },
    budget: {
        label: 'Ngân sách vận hành',
        color: AI_CHART_SLATE,
        fill: 'transparent',
        borderWidth: 2,
        borderDash: [6, 4],
        fillArea: false,
    },
    previous_year: {
        label: 'Cùng kỳ năm trước',
        color: AI_CHART_SKY,
        fill: 'rgba(24, 95, 165, 0.06)',
        borderWidth: 2,
        borderDash: [2, 3],
        fillArea: false,
    },
};

export const DONUT_PALETTE = {
    budget: [AI_CHART_BRAND, AI_CHART_AMBER, '#cbd5e1'],
    status: [AI_CHART_EMERALD, AI_CHART_AMBER, AI_CHART_ROSE, '#94a3b8'],
};

export function moneyTooltipLabel(context) {
    const v = context.parsed?.y ?? context.parsed ?? context.raw;
    return ` ${context.dataset.label}: ${formatVndCompact(v)}`;
}

export function countTooltipLabel(context) {
    const v = context.parsed?.y ?? context.parsed ?? context.raw;
    return ` ${context.dataset.label}: ${v} tài khoản`;
}

export function donutTooltipLabel(context) {
    const v = context.parsed ?? context.raw;
    const label = context.label ?? '';
    return ` ${label}: ${typeof v === 'number' && v > 1_000_000 ? formatVndCompact(v) : v}`;
}

export const baseCartesianScales = {
    x: {
        grid: { display: false },
        ticks: { maxRotation: 0, font: { size: 11 }, color: '#64748b' },
    },
    y: {
        beginAtZero: true,
        grid: { color: 'rgba(148, 163, 184, 0.2)' },
        ticks: {
            font: { size: 11 },
            color: '#64748b',
            callback: (v) => formatVndCompact(v),
        },
    },
};

export const baseChartPlugins = {
    legend: { display: false },
    tooltip: {
        padding: 12,
        boxPadding: 4,
        backgroundColor: 'rgba(15, 23, 42, 0.92)',
        titleFont: { size: 12, weight: '600' },
        bodyFont: { size: 11 },
        cornerRadius: 8,
    },
};
