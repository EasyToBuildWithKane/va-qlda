/**
 * Palette brand + tiện ích cho chart.js dùng chung trong module Hiệu suất.
 * Giữ một nguồn màu duy nhất (khớp token Tailwind `slate/sky/violet/...`).
 */
export const BRAND = '#9A0036';
export const BRAND_SOFT = 'rgba(154,0,54,0.08)';

export const colorMap = {
    slate: '#94a3b8',
    sky: '#0ea5e9',
    violet: '#8b5cf6',
    emerald: '#10b981',
    rose: '#f43f5e',
    amber: '#f59e0b',
    brand: BRAND,
};

export function tailwindToHex(color) {
    return colorMap[color] ?? colorMap.slate;
}

/** Tông màu mềm (nền) tương ứng cho tone. */
export const toneSoft = {
    brand: 'bg-brand/10 text-brand',
    sky: 'bg-sky-100 text-sky-600',
    violet: 'bg-violet-100 text-violet-600',
    emerald: 'bg-emerald-100 text-emerald-600',
    rose: 'bg-rose-100 text-rose-600',
    amber: 'bg-amber-100 text-amber-600',
};

/** Map level insight → màu hiển thị. */
export const insightTone = {
    success: { ring: 'border-emerald-200 bg-emerald-50', icon: 'text-emerald-600', dot: 'bg-emerald-500' },
    info: { ring: 'border-sky-200 bg-sky-50', icon: 'text-sky-600', dot: 'bg-sky-500' },
    warning: { ring: 'border-amber-200 bg-amber-50', icon: 'text-amber-600', dot: 'bg-amber-500' },
};

export function baseLineOptions(overrides = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { padding: 10, boxPadding: 4 },
        },
        scales: {
            x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } },
            y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.18)' } },
        },
        ...overrides,
    };
}
