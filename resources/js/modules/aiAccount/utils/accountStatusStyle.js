/** Màu chữ/select trạng thái (không dùng Badge). */
export function statusSelectClass(status) {
    const map = {
        active: 'border-emerald-200 bg-emerald-50/80 text-emerald-800',
        expiring_soon: 'border-amber-300 bg-amber-50 text-amber-900',
        expired: 'border-rose-300 bg-rose-50 text-rose-800',
        cancelled: 'border-slate-200 bg-slate-50 text-slate-600',
    };
    return map[status] ?? 'border-slate-200 bg-white text-slate-700';
}

export function statusTextClass(status) {
    const map = {
        active: 'text-emerald-700',
        expiring_soon: 'text-amber-800 font-semibold',
        expired: 'text-rose-700 font-semibold',
        cancelled: 'text-slate-500',
    };
    return map[status] ?? 'text-slate-700';
}
