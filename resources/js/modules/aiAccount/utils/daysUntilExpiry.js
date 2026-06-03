/**
 * Số ngày từ hôm nay đến ngày hết hạn (YYYY-MM-DD). Âm nếu đã qua hạn.
 */
export function daysUntilExpiryFromDate(dateStr) {
    if (!dateStr) return null;
    const end = new Date(`${String(dateStr).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(end.getTime())) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((end - today) / 86_400_000);
}

/**
 * @param {number|null|undefined} days
 * @param {string|null|undefined} status
 * @returns {{ text: string, urgent: boolean }|null}
 */
export function formatDaysLeftLabel(days, status) {
    if (days == null || !Number.isFinite(days)) return null;

    if (status === 'expired' || days < 0) {
        const over = Math.abs(days);
        return {
            text: over > 0 ? `Quá hạn ${over} ngày` : 'Đã hết hạn',
            urgent: true,
        };
    }
    if (days === 0) {
        return { text: 'Hết hạn hôm nay', urgent: true };
    }
    if (days === 1) {
        return { text: 'Còn 1 ngày', urgent: true };
    }

    return {
        text: `Còn ${days} ngày`,
        urgent: status === 'expiring_soon' || days <= 14,
    };
}

/**
 * @param {{ expiry_date?: string, end_date?: string, days_until_expiry?: number, status?: string }} row
 */
export function resolveDaysLeft(row) {
    if (row.days_until_expiry_signed != null && Number.isFinite(row.days_until_expiry_signed)) {
        return row.days_until_expiry_signed;
    }
    if (row.status === 'expired' && row.days_until_expiry === 0) {
        const fromDate = daysUntilExpiryFromDate(row.expiry_date ?? row.end_date);
        if (fromDate != null && fromDate < 0) return fromDate;
        return 0;
    }
    if (row.days_until_expiry != null && Number.isFinite(row.days_until_expiry)) {
        return row.days_until_expiry;
    }
    const date = row.expiry_date ?? row.end_date;
    return daysUntilExpiryFromDate(date);
}
