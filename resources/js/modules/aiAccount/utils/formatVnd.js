/**
 * Số tiền hiển thị: 1.000.000
 * @param {number|string} amount
 */
export function formatVndNumber(amount) {
    const n = Math.round(Number(amount) || 0);
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(n);
}

/**
 * Đọc ngắn: 1 triệu, 1,5 triệu, 2 tỷ, 500 nghìn
 * @param {number|string} amount
 */
export function formatVndHumanShort(amount) {
    const n = Math.round(Number(amount) || 0);
    if (n === 0) return '0 đồng';

    if (n >= 1_000_000_000) {
        return `${formatDecimalVi(n / 1_000_000_000)} tỷ`;
    }
    if (n >= 1_000_000) {
        return `${formatDecimalVi(n / 1_000_000)} triệu`;
    }
    if (n >= 1_000) {
        return `${formatDecimalVi(n / 1_000)} nghìn`;
    }
    return `${formatVndNumber(n)} đồng`;
}

/**
 * @param {number|string} amount
 * @returns {{ primary: string, secondary: string|null, full: string }}
 */
export function formatVndDisplay(amount) {
    const n = Math.round(Number(amount) || 0);
    const primary = `${formatVndNumber(n)} VNĐ`;
    const human = formatVndHumanShort(n);
    const secondary = n > 0 ? `(${human} VNĐ)` : null;
    return {
        primary,
        secondary,
        full: secondary ? `${primary} ${secondary}` : primary,
    };
}

function formatDecimalVi(value) {
    if (Number.isInteger(value)) return String(value);
    return value.toFixed(1).replace('.', ',');
}

/**
 * @param {number} amount
 * @returns {string}
 */
export function formatVnd(amount) {
    return formatVndDisplay(amount).primary;
}

/**
 * Hiển thị gọn trên dashboard: 4,2 triệu vnđ
 * @param {number|string} amount
 */
export function formatVndCompact(amount) {
    const n = Math.round(Number(amount) || 0);
    if (n === 0) return '0 vnđ';
    const human = formatVndHumanShort(n);
    if (human.endsWith(' đồng')) {
        return `${human.slice(0, -' đồng'.length)} vnđ`;
    }
    return `${human} vnđ`;
}

/**
 * @param {'monthly'|'yearly'|'one_time'} unit
 */
export function costUnitSuffix(unit) {
    if (unit === 'monthly') return '/ tháng';
    if (unit === 'yearly') return '/ năm';
    return '(một lần)';
}

/**
 * @param {number} amount
 * @param {'monthly'|'yearly'|'one_time'} unit
 */
export function formatCostWithUnit(amount, unit) {
    const { primary } = formatVndDisplay(amount);
    return `${primary} ${costUnitSuffix(unit)}`.trim();
}

/**
 * @param {number} amount
 * @param {'monthly'|'yearly'|'one_time'} unit
 * @param {number} monthlyEquivalent
 */
export function formatCostCell(amount, unit, monthlyEquivalent) {
    const main = formatCostWithUnit(amount, unit);
    if (unit === 'yearly' && monthlyEquivalent > 0) {
        const eq = formatVndDisplay(monthlyEquivalent);
        return `${main} (~${eq.primary} / tháng)`;
    }
    return main;
}
