/** HTML từ TipTap / rich editor (khác plain text có xuống dòng). */
export function isRichHtmlContent(value) {
    const s = String(value ?? '').trim();
    return /<[a-z][\s\S]*>/i.test(s);
}

/** Plain text để preview (card line-clamp) hoặc fallback SSR. */
export function richContentPlainText(value) {
    const s = String(value ?? '').trim();
    if (!s) {
        return '';
    }
    if (!isRichHtmlContent(s)) {
        return s;
    }
    return s
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<\/li>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}
