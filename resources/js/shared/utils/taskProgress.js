/** Tiến độ task cố định theo trạng thái — đồng bộ với App\Support\TaskProgress. */
export function taskProgressFromStatus(status) {
    const value = typeof status === 'object' && status != null ? status.value : status;
    switch (value) {
    case 'done':
        return 100;
    case 'in_review':
        return 66;
    case 'in_progress':
        return 33;
    default:
        return 0;
    }
}

/** % task gốc đã hoàn thành (trạng thái done). */
export function completionPercentFromTasks(tasks, isDone = (t) => t?.status?.value === 'done') {
    const list = tasks ?? [];
    if (!list.length) return 0;
    const done = list.filter(isDone).length;
    return Math.round((done / list.length) * 100);
}
