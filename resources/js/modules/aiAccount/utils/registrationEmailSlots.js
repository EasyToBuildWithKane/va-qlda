export function staffSlotsFromCount(raw) {
    const n = parseInt(String(raw ?? '1'), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
}

/** @param {string[]} slots */
export function syncRegistrationEmailSlots(slots, staffCount) {
    const n = staffSlotsFromCount(staffCount);
    const next = [...(slots ?? [])];
    while (next.length < n) next.push('');
    if (next.length > n) next.length = n;
    return next;
}

export function registrationEmailsFromRow(row, staffCount) {
    const n = staffSlotsFromCount(staffCount ?? row?.staff_count);
    const list = Array.isArray(row?.registration_emails) ? [...row.registration_emails] : [];
    const out = syncRegistrationEmailSlots(list, n);
    if (!out[0]?.trim() && row?.registration_email) {
        out[0] = row.registration_email;
    }
    return out;
}
