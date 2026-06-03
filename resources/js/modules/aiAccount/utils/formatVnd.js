/**
 * @param {number} amount
 * @returns {string}
 */
export function formatVnd(amount) {
    const n = Number(amount) || 0;
    const formatted = new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(n);
    return formatted.replace(/\s*₫\s*$/, ' đ').trim();
}

/**
 * @param {number} amount
 * @param {'monthly'|'yearly'|'one_time'} unit
 */
export function formatCostWithUnit(amount, unit) {
    const base = formatVnd(amount);
    if (unit === 'monthly') return `${base} / tháng`;
    if (unit === 'yearly') return `${base} / năm`;
    return `${base} (một lần)`;
}

/**
 * @param {number} amount
 * @param {'monthly'|'yearly'|'one_time'} unit
 * @param {number} monthlyEquivalent
 */
export function formatCostCell(amount, unit, monthlyEquivalent) {
    const main = formatCostWithUnit(amount, unit);
    if (unit === 'yearly' && monthlyEquivalent > 0) {
        return `${main} (~${formatVnd(monthlyEquivalent)} / tháng)`;
    }
    return main;
}
