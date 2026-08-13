// Shared display formatters for the project-management UI (Vietnamese locale).

const vnd = new Intl.NumberFormat('vi-VN');

/** Format a number as VND, e.g. 1280000 → "1.280.000 ₫". null → "—". */
export function currency(value) {
    if (value === null || value === undefined || value === '') return '—';
    return vnd.format(Math.round(Number(value))) + ' ₫';
}

/** Plain grouped number, e.g. 1280000 → "1.280.000". */
export function number(value) {
    if (value === null || value === undefined || value === '') return '—';
    return vnd.format(Number(value));
}

/** Parse `Y-m-d` as calendar date (no UTC shift). */
export function dateFromYmd(ymd) {
    if (!ymd) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(ymd).trim());
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return Number.isNaN(d.getTime()) ? null : d;
}

/** ISO date/datetime → "dd/mm/yyyy" (mốc lịch, báo cáo). null → nhãn trống caller xử lý. */
export function dateAtMidnight(value) {
    return date(value);
}

/** ISO date/datetime → "dd/mm/yyyy". null → "—". */
export function date(value) {
    if (!value) return '—';
    const d = String(value).length === 10 ? dateFromYmd(value) : new Date(value);
    if (!d || Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('vi-VN');
}

/** Calendar date with weekday, e.g. "Thứ Hai, 08/06/2026". */
export function dateLongVi(value) {
    const d = typeof value === 'string' && value.length === 10 ? dateFromYmd(value) : new Date(value);
    if (!d || Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('vi-VN', {
        weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric',
    });
}

/** "HH:mm" hoặc "HH:mm:ss" → "HH:mm". */
export function timeOfDay(value) {
    if (value == null || value === '') return '—';
    const s = String(value).trim();
    const m = s.match(/^(\d{1,2}):(\d{2})/);
    if (!m) return s;
    return `${m[1].padStart(2, '0')}:${m[2]}`;
}

/** ISO datetime → "dd/mm/yyyy HH:mm". */
export function datetime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

/** Compact hours, e.g. 16.5 → "16,5h". */
export function hours(value) {
    if (value === null || value === undefined || value === '') return '0h';
    return Number(value).toLocaleString('vi-VN') + 'h';
}

const ONES = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

/** Read a 3-digit group (0-999) in Vietnamese. `full` forces the leading "không trăm". */
function readGroup(n, full) {
    const tram = Math.floor(n / 100);
    const chuc = Math.floor((n % 100) / 10);
    const donvi = n % 10;
    let out = '';

    if (tram > 0 || full) out += ONES[tram] + ' trăm';

    if (chuc > 1) {
        out += ' ' + ONES[chuc] + ' mươi';
        if (donvi === 1) out += ' mốt';
        else if (donvi === 5) out += ' lăm';
        else if (donvi > 0) out += ' ' + ONES[donvi];
    } else if (chuc === 1) {
        out += ' mười';
        if (donvi === 5) out += ' lăm';
        else if (donvi > 0) out += ' ' + ONES[donvi];
    } else if (donvi > 0) {
        // hàng chục = 0
        if (tram > 0 || full) out += ' lẻ ' + ONES[donvi];
        else out += ONES[donvi];
    }

    return out.trim();
}

/**
 * Vietnamese amount-in-words for a VND value, e.g. 50000000 → "Năm mươi triệu đồng".
 * Handles up to hundreds of billions; returns '' for empty/0.
 */
export function dongToWords(value) {
    let n = Math.floor(Number(value) || 0);
    if (n <= 0) return '';

    const scales = ['', ' nghìn', ' triệu', ' tỷ'];
    const groups = [];
    while (n > 0) {
        groups.unshift(n % 1000);
        n = Math.floor(n / 1000);
    }

    const total = groups.length;
    let words = '';
    groups.forEach((g, i) => {
        const fromRight = total - 1 - i;
        const scale = scales[fromRight % 4] ?? '';
        // tỷ repeats for very large numbers — keep simple, ignore >999 tỷ edge
        if (g === 0) return;
        const full = i > 0; // non-leading groups read all three digits
        words += ' ' + readGroup(g, full) + scale;
    });

    words = words.trim() + ' đồng';
    return words.charAt(0).toUpperCase() + words.slice(1);
}

export function useFormat() {
    return { currency, number, date, dateAtMidnight, datetime, hours, timeOfDay, dongToWords };
}
