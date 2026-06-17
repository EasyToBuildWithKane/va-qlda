/**
 * Số ngày lịch từ hôm nay đến ngày hết hạn (YYYY-MM-DD). Âm nếu đã qua hạn.
 */
export function credentialDaysUntilExpiry(dateStr) {
    if (!dateStr) return null;
    const end = new Date(`${String(dateStr).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(end.getTime())) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((end - today) / 86_400_000);
}

/** Thời điểm hết hạn: cuối ngày theo giờ local. */
export function credentialExpiryEndMs(dateStr) {
    if (!dateStr) return null;
    const end = new Date(`${String(dateStr).slice(0, 10)}T23:59:59`);
    return Number.isNaN(end.getTime()) ? null : end.getTime();
}

export function isCredentialExpiringWithinDays(dateStr, withinDays = 7) {
    const days = credentialDaysUntilExpiry(dateStr);
    if (days == null) return false;
    return days >= 0 && days <= withinDays;
}

/**
 * @returns {{ text: string, expired: boolean }|null}
 */
export function formatCredentialExpiryCountdown(dateStr, nowMs = Date.now()) {
    const end = credentialExpiryEndMs(dateStr);
    if (end == null) return null;
    const diff = end - nowMs;
    if (diff <= 0) {
        return { text: 'Đã hết hạn', expired: true };
    }
    const totalSec = Math.floor(diff / 1000);
    const days = Math.floor(totalSec / 86_400);
    const h = Math.floor((totalSec % 86_400) / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    const pad = (n) => String(n).padStart(2, '0');
    const clock = `${pad(h)}:${pad(m)}:${pad(s)}`;
    if (days > 0) {
        return { text: `Còn ${days} ngày ${clock}`, expired: false };
    }
    return { text: `Còn ${clock}`, expired: false };
}
