/** @typedef {'due_date'|'estimate'} TaskLateReason */

export const TASK_ACTIVE_STATUSES = ['in_progress', 'in_review', 'blocked'];

const startOfToday = () => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
};

const parseDate = (x) => (x ? new Date(`${x}T00:00:00`) : null);

const parseDateTime = (x) => {
    if (!x) return null;
    const d = new Date(x);
    return Number.isNaN(d.getTime()) ? null : d;
};

export function isTaskDone(t) {
    return t?.status?.value === 'done';
}

/**
 * Thời điểm bắt đầu tính SLA giờ ước tính (ưu tiên work_started_at từ server).
 * @param {object} t
 * @returns {Date|null}
 */
export function getTaskWorkStartedAt(t) {
    const explicit = parseDateTime(t?.work_started_at);
    if (explicit) return explicit;

    if (!TASK_ACTIVE_STATUSES.includes(t?.status?.value)) return null;

    const fromStartDate = parseDate(t?.start_date);
    if (fromStartDate) {
        fromStartDate.setHours(9, 0, 0, 0);
        return fromStartDate;
    }

    return parseDateTime(t?.created_at);
}

/**
 * Hạn theo giờ ước tính = work_started_at + estimate_hours.
 * @param {object} t
 * @returns {Date|null}
 */
export function getTaskEstimateDeadline(t) {
    const hours = Number(t?.estimate_hours);
    if (!hours || hours <= 0) return null;
    const started = getTaskWorkStartedAt(t);
    if (!started) return null;
    return new Date(started.getTime() + hours * 60 * 60 * 1000);
}

/** Quá hạn theo ngày (due_date). */
export function isTaskDateOverdue(t) {
    const due = parseDate(t?.due_date);
    return !!(due && due < startOfToday() && !isTaskDone(t));
}

/** Vượt giờ ước tính so với thời điểm bắt đầu làm. */
export function isTaskEstimateOverrun(t) {
    const deadline = getTaskEstimateDeadline(t);
    if (!deadline) return false;

    if (isTaskDone(t)) {
        const finished = parseDateTime(t?.updated_at);
        return !!(finished && finished > deadline);
    }

    if (!TASK_ACTIVE_STATUSES.includes(t?.status?.value)) return false;

    return new Date() > deadline;
}

/** Trễ (ngày hoặc giờ ước tính). */
export function isTaskOverdue(t) {
    return isTaskDateOverdue(t) || isTaskEstimateOverrun(t);
}

/**
 * @param {object} t
 * @returns {TaskLateReason[]}
 */
export function getTaskLateReasons(t) {
    /** @type {TaskLateReason[]} */
    const reasons = [];
    if (isTaskDateOverdue(t)) reasons.push('due_date');
    if (isTaskEstimateOverrun(t)) reasons.push('estimate');
    return reasons;
}

/**
 * @param {object} t
 * @returns {string|null}
 */
export function getTaskLateLabel(t) {
    const reasons = getTaskLateReasons(t);
    if (!reasons.length) return null;
    if (reasons.includes('due_date') && reasons.includes('estimate')) {
        return 'Trễ hạn & vượt giờ';
    }
    if (reasons.includes('estimate')) return 'Vượt giờ ước tính';
    return 'Quá hạn';
}

/**
 * Số giờ đã trễ so với deadline ước tính (làm tròn 0.5h).
 * @param {object} t
 * @returns {number|null}
 */
export function getTaskEstimateOverrunHours(t) {
    const deadline = getTaskEstimateDeadline(t);
    if (!deadline) return null;
    const ref = isTaskDone(t) ? parseDateTime(t?.updated_at) : new Date();
    if (!ref || ref <= deadline) return null;
    const h = (ref - deadline) / (1000 * 60 * 60);
    return Math.round(h * 2) / 2;
}

const formatSlaDateTime = (d) => {
    if (!d) return '—';
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })
        + ' '
        + d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

/**
 * Trạng thái SLA giờ ước tính cho cột bảng sprint.
 * @param {object} t
 * @returns {{ label: string, detail: string, tone: 'muted'|'ok'|'warn'|'danger' }}
 */
export function getTaskSlaState(t) {
    const est = Number(t?.estimate_hours);
    if (!est || est <= 0) {
        return { label: '—', detail: 'Chưa có giờ ước tính', tone: 'muted' };
    }

    const deadline = getTaskEstimateDeadline(t)
        || (t?.estimate_deadline_at ? parseDateTime(t.estimate_deadline_at) : null);

    if (!deadline) {
        return {
            label: 'Chờ bắt đầu',
            detail: `SLA ${est}h sau khi chuyển Đang làm`,
            tone: 'muted',
        };
    }

    const deadlineText = formatSlaDateTime(deadline);

    if (isTaskEstimateOverrun(t)) {
        const over = getTaskEstimateOverrunHours(t);
        return {
            label: 'Quá SLA',
            detail: over ? `Vượt ${over}h · hạn ${deadlineText}` : `Hạn ${deadlineText}`,
            tone: 'danger',
        };
    }

    if (isTaskDone(t)) {
        return {
            label: 'Đúng SLA',
            detail: `Hạn ${deadlineText}`,
            tone: 'ok',
        };
    }

    if (!TASK_ACTIVE_STATUSES.includes(t?.status?.value)) {
        return {
            label: 'Chưa chạy',
            detail: `Hạn dự kiến ${deadlineText}`,
            tone: 'muted',
        };
    }

    const remainingH = (deadline.getTime() - Date.now()) / (1000 * 60 * 60);
    if (remainingH <= 0.5) {
        const mins = Math.max(1, Math.round(remainingH * 60));
        return {
            label: 'Sắp hết',
            detail: `Còn ${mins} phút · ${deadlineText}`,
            tone: 'warn',
        };
    }

    return {
        label: 'Trong SLA',
        detail: `Còn ${remainingH.toFixed(1)}h · ${deadlineText}`,
        tone: 'ok',
    };
}

const SLA_TONE_CLASS = {
    muted: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    ok: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
    warn: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
    danger: 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
};

export function getTaskSlaToneClass(tone) {
    return SLA_TONE_CLASS[tone] || SLA_TONE_CLASS.muted;
}
