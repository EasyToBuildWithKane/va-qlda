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

function formatDecimalVi(value) {
    if (Number.isInteger(value)) return String(value);
    return value.toFixed(1).replace('.', ',');
}

/** Đọc ngắn tiếng Việt: 1 triệu, 1,5 triệu, 2 tỷ, 500 nghìn. */
export function formatVndWords(amount) {
    const n = Math.round(Number(amount) || 0);
    if (n === 0) return '0 đồng';
    if (Math.abs(n) >= 1_000_000_000) return `${formatDecimalVi(n / 1_000_000_000)} tỷ`;
    if (Math.abs(n) >= 1_000_000) return `${formatDecimalVi(n / 1_000_000)} triệu`;
    if (Math.abs(n) >= 1_000) return `${formatDecimalVi(n / 1_000)} nghìn`;
    return `${vndFormatter.format(n)} đồng`;
}

/**
 * Hiển thị đầy đủ: { primary: "1.000.000 VNĐ", secondary: "(1 triệu VNĐ)", full }.
 * @returns {{ primary: string, secondary: string|null, full: string }}
 */
export function formatVndDisplay(amount, currency = 'VNĐ') {
    const n = Math.round(Number(amount) || 0);
    const primary = `${vndFormatter.format(n)} ${currency}`;
    const secondary = n !== 0 ? `(${formatVndWords(n)} ${currency})` : null;
    return { primary, secondary, full: secondary ? `${primary} ${secondary}` : primary };
}

/** Số có dấu chấm phân nhóm: 1.000.000 (không kèm đơn vị). */
export function formatVndNumber(amount) {
    return vndFormatter.format(Math.round(Number(amount) || 0));
}

/**
 * Đọc số tiền thành chữ tiếng Việt đầy đủ.
 * Ví dụ: 120_000_000 → "Một trăm hai mươi triệu đồng".
 */
export function vndToWords(amount) {
    const n = Math.round(Number(amount) || 0);
    if (n === 0) return 'Không đồng';
    if (n < 0) return `Âm ${vndToWords(-n)}`;

    const units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    function readHundreds(num) {
        const h = Math.floor(num / 100);
        const rest = num % 100;
        const t = Math.floor(rest / 10);
        const u = rest % 10;
        let result = '';
        if (h > 0) result += units[h] + ' trăm';
        if (t === 0 && u === 0) return result.trim();
        if (t === 0 && u > 0) result += (h > 0 ? ' lẻ ' : '') + units[u];
        else if (t === 1) result += (result ? ' mười' : 'mười') + (u > 0 ? (u === 5 ? ' lăm' : ' ' + units[u]) : '');
        else {
            result += (result ? ' ' : '') + units[t] + ' mươi';
            if (u === 1) result += ' mốt';
            else if (u === 5) result += ' lăm';
            else if (u > 0) result += ' ' + units[u];
        }
        return result.trim();
    }

    const tiers = [
        { value: 1_000_000_000_000, label: 'nghìn tỷ' },
        { value: 1_000_000_000, label: 'tỷ' },
        { value: 1_000_000, label: 'triệu' },
        { value: 1_000, label: 'nghìn' },
        { value: 1, label: '' },
    ];

    let parts = [];
    let remaining = n;
    for (const tier of tiers) {
        const qty = Math.floor(remaining / tier.value);
        if (qty > 0) {
            const words = readHundreds(qty > 999 ? qty % 1000 : qty);
            parts.push(words + (tier.label ? ' ' + tier.label : ''));
            remaining -= qty * tier.value;
        }
    }

    const joined = parts.join(' ').replace(/\s+/g, ' ').trim();
    return joined.charAt(0).toUpperCase() + joined.slice(1) + ' đồng';
}

/** Parse chuỗi tiền có dấu phân nhóm về số (giữ dấu trừ). */
export function parseVndNumber(input) {
    if (input === null || input === undefined) return null;
    const digits = String(input).replace(/[^\d-]/g, '');
    if (digits === '' || digits === '-') return null;
    const n = Number(digits);
    return Number.isNaN(n) ? null : n;
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

const FILE_EXT_LABELS = {
    pdf: 'PDF',
    doc: 'Word',
    docx: 'Word',
    xls: 'Excel',
    xlsx: 'Excel',
    ppt: 'PowerPoint',
    pptx: 'PowerPoint',
    png: 'Ảnh PNG',
    jpg: 'Ảnh JPEG',
    jpeg: 'Ảnh JPEG',
    webp: 'Ảnh WebP',
    zip: 'Nén ZIP',
    txt: 'Văn bản',
};

/** Nhãn loại file từ tên hoặc MIME (form upload / preview). */
export function fileKindLabel(name, mimeType = '') {
    const base = String(name ?? '').trim();
    const ext = base.includes('.') ? base.split('.').pop()?.toLowerCase() : '';
    if (ext && FILE_EXT_LABELS[ext]) return FILE_EXT_LABELS[ext];
    if (mimeType) {
        const sub = mimeType.split('/')[1];
        if (sub) return sub.toUpperCase();
    }
    if (/^https?:\/\//i.test(base)) return 'Link ngoài';
    return ext ? ext.toUpperCase() : 'Tệp';
}
