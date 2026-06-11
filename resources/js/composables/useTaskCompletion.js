const HOURS_EPSILON = 0.05;

/**
 * @param {number|null|undefined} estimate
 * @param {number} actual
 * @returns {'early'|'on_plan'|'over_plan'|null}
 */
export function resolveHoursTiming(estimate, actual) {
    const est = Number(estimate);
    const act = Number(actual);
    if (!est || est <= 0 || !Number.isFinite(act)) return null;
    if (act < est - HOURS_EPSILON) return 'early';
    if (act > est + HOURS_EPSILON) return 'over_plan';
    return 'on_plan';
}

const HOURS_TIMING_META = {
    early: { label: 'Sớm hơn dự kiến', color: 'emerald' },
    on_plan: { label: 'Đúng kế hoạch', color: 'amber' },
    over_plan: { label: 'Vượt thời gian dự kiến', color: 'rose' },
};

const SLA_META = {
    met: { label: 'Đạt SLA', color: 'emerald' },
    exceeded: { label: 'Vượt SLA', color: 'rose' },
};

export function hoursTimingMeta(value) {
    return HOURS_TIMING_META[value] || null;
}

export function slaResultMeta(value) {
    return SLA_META[value] || null;
}

/**
 * Badge SLA / kế hoạch cho task đã hoàn thành (ưu tiên sla_result từ server).
 * @param {object} t
 */
export function getTaskCompletionBadge(t) {
    if (t?.status?.value !== 'done') return null;

    if (t?.sla_result?.label) {
        const tone = t.sla_result.value === 'met'
            ? (t.hours_timing?.value === 'early' ? 'emerald' : t.hours_timing?.value === 'on_plan' ? 'amber' : 'emerald')
            : 'rose';
        return {
            label: t.sla_result.label,
            detail: completionDetailLine(t),
            tone: t.sla_result.value === 'exceeded' ? 'danger' : tone === 'amber' ? 'warn' : 'ok',
            color: t.sla_result.color,
        };
    }

    const timing = t?.hours_timing?.value;
    if (timing) {
        const meta = hoursTimingMeta(timing);
        return {
            label: meta?.label ?? timing,
            detail: completionDetailLine(t),
            tone: timing === 'over_plan' ? 'danger' : timing === 'on_plan' ? 'warn' : 'ok',
            color: meta?.color,
        };
    }

    return null;
}

function completionDetailLine(t) {
    const est = t?.estimate_hours;
    const act = t?.actual_hours;
    if (est != null && act != null) return `ƯT ${est}h · TT ${act}h`;
    if (act != null) return `Thực tế ${act}h`;
    return '';
}

export function isTaskStatusLocked(task) {
    return !!task?.status_locked;
}

export function canChangeTaskStatus(task, canContribute, isAdmin) {
    if (!canContribute) return false;
    if (task?.status?.value === 'done' && !isAdmin && task?.status_locked !== false) {
        return task?.can_change_status === true ? true : !task?.status_locked;
    }
    return task?.can_change_status !== false;
}
