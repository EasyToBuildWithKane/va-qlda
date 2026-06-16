/**
 * Tiện ích định dạng dùng chung cho module CLM (tiền tệ, ngày, số ngày tới hạn).
 */

const vndFormatter = new Intl.NumberFormat('vi-VN');

export function formatMoney(value, currency = 'VND') {
    if (value === null || value === undefined || value === '') return '—';
    const num = Number(value);
    if (Number.isNaN(num)) return '—';
    return `${vndFormatter.format(Math.round(num))} ${currency}`;
}

export function formatMoneyShort(value, currency = 'VND') {
    if (value === null || value === undefined || value === '') return '—';
    const num = Number(value);
    if (Number.isNaN(num)) return '—';
    const abs = Math.abs(num);
    if (abs >= 1_000_000_000) return `${(num / 1_000_000_000).toFixed(1)} tỷ ${currency}`;
    if (abs >= 1_000_000) return `${(num / 1_000_000).toFixed(1)} tr ${currency}`;
    return `${vndFormatter.format(Math.round(num))} ${currency}`;
}

export function formatDate(value) {
    if (!value) return '—';
    const d = new Date(`${value}T00:00:00`);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('vi-VN');
}

/** Nhãn ngắn cho số ngày còn lại tới hạn. */
export function expiryLabel(days) {
    if (days === null || days === undefined) return 'Không hạn';
    if (days < 0) return `Quá hạn ${Math.abs(days)} ngày`;
    if (days === 0) return 'Hết hạn hôm nay';
    return `Còn ${days} ngày`;
}

/** Tone (màu) cho số ngày còn lại. */
export function expiryTone(days) {
    if (days === null || days === undefined) return 'slate';
    if (days < 0) return 'rose';
    if (days <= 7) return 'rose';
    if (days <= 30) return 'amber';
    if (days <= 90) return 'sky';
    return 'emerald';
}
