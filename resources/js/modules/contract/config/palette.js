/** Brand + tone → hex cho chart.js trong module CLM (khớp token Tailwind). */
export const BRAND = '#9A0036';

export const toneHex = {
    slate: '#94a3b8',
    sky: '#0ea5e9',
    violet: '#8b5cf6',
    emerald: '#10b981',
    rose: '#f43f5e',
    amber: '#f59e0b',
    brand: BRAND,
};

export function hexFor(tone) {
    return toneHex[tone] ?? toneHex.slate;
}
