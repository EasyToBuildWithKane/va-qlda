/**
 * Đối soát vướng mắc — pure function, không gọi API.
 *
 * Trả về danh sách vấn đề { level, code, message, blockerId? }
 * và summary { total, errors, warnings, info }.
 */

const TERMINAL = new Set(['resolved', 'closed']);

/**
 * @param {object[]} blockers — danh sách vướng mắc đã load
 * @returns {{ issues: object[], summary: object }}
 */
export function reconcileBlockers(blockers) {
    const issues = [];

    const addIssue = (level, code, message, blockerId = null) => {
        issues.push({ level, code, message, blockerId });
    };

    const now = new Date();
    now.setHours(0, 0, 0, 0);

    for (const b of blockers) {
        const status = b.status?.value ?? '';
        const isOpen = !TERMINAL.has(status);

        // VM-01: Vướng mắc chưa có người phụ trách
        if (isOpen && !b.owner?.id) {
            addIssue('warning', 'no_owner',
                `"${b.title}" chưa gán người phụ trách.`, b.id);
        }

        // VM-02: Vướng mắc mở đã quá hạn
        if (isOpen && b.due_date) {
            const due = new Date(`${b.due_date}T00:00:00`);
            if (due < now) {
                addIssue('error', 'overdue_open',
                    `"${b.title}" quá hạn xử lý (${b.due_date}).`, b.id);
            }
        }

        // VM-03: Đã giải quyết nhưng không có hướng xử lý
        if (TERMINAL.has(status) && !b.resolution?.trim()) {
            addIssue('warning', 'resolved_no_resolution',
                `"${b.title}" đã đóng nhưng chưa ghi hướng xử lý.`, b.id);
        }

        // VM-04: Nghiêm trọng mở quá 7 ngày không cập nhật
        if (isOpen && b.severity?.value === 'critical' && b.updated_at) {
            const updated = new Date(b.updated_at);
            const ageDays = (now - updated) / (1000 * 60 * 60 * 24);
            if (ageDays > 7) {
                addIssue('error', 'critical_stale',
                    `"${b.title}" (nghiêm trọng) không được cập nhật hơn ${Math.floor(ageDays)} ngày.`, b.id);
            }
        }

        // VM-05: Tiêu đề trùng (case-insensitive)
        const titleKey = b.title?.trim().toLowerCase();
        if (titleKey) {
            const dup = blockers.find((x) => x.id !== b.id && x.title?.trim().toLowerCase() === titleKey);
            if (dup && b.id < dup.id) {
                addIssue('info', 'duplicate_title',
                    `Tiêu đề "${b.title}" trùng với mục khác (ID ${dup.id}).`, b.id);
            }
        }
    }

    const errors = issues.filter((i) => i.level === 'error').length;
    const warnings = issues.filter((i) => i.level === 'warning').length;
    const info = issues.filter((i) => i.level === 'info').length;

    return {
        issues,
        summary: { total: issues.length, errors, warnings, info },
    };
}
