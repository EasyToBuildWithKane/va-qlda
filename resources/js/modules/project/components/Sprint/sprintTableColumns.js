/** Toggleable columns for Sprint task table (ID + title always shown). */

export const SPRINT_TABLE_COLUMNS = [
    { key: 'sprint', label: 'Sprint', sortKey: 'sprint' },
    { key: 'status', label: 'Trạng thái', sortKey: 'status' },
    { key: 'priority', label: 'Ưu tiên', sortKey: 'priority' },
    { key: 'phase', label: 'Giai đoạn', sortKey: 'phase' },
    { key: 'assignee', label: 'Người làm', sortKey: 'assignee' },
    { key: 'reviewer', label: 'Người duyệt', sortKey: 'reviewer' },
    { key: 'estimate_hours', label: 'Giờ ước tính', sortKey: 'estimate_hours' },
    { key: 'actual_hours', label: 'Giờ thực tế', sortKey: 'actual_hours' },
    { key: 'sla', label: 'SLA', sortKey: null },
    { key: 'progress', label: 'Tiến độ', sortKey: 'progress' },
    { key: 'start_date', label: 'Bắt đầu', sortKey: 'start_date' },
    { key: 'due_date', label: 'Hạn', sortKey: 'due_date' },
    { key: 'logged_hours', label: 'Giờ đã log', sortKey: 'logged_hours' },
    { key: 'sprint_goal', label: 'Mục tiêu Sprint' },
];

export const SPRINT_TABLE_DEFAULT_VISIBLE = [
    'sprint',
    'status',
    'priority',
    'assignee',
    'start_date',
    'due_date',
    'estimate_hours',
    'actual_hours',
    'sla',
    'progress',
];

export const SPRINT_TABLE_COLS_KEY = 'va-qlda.sprint-table.columns';

export function loadSprintTableColumns() {
    const defaults = [...SPRINT_TABLE_DEFAULT_VISIBLE];
    try {
        const saved = JSON.parse(localStorage.getItem(SPRINT_TABLE_COLS_KEY));
        if (Array.isArray(saved) && saved.length) {
            const valid = saved.filter((k) => SPRINT_TABLE_COLUMNS.some((c) => c.key === k));
            defaults.forEach((k) => {
                if (!valid.includes(k)) valid.push(k);
            });
            return valid;
        }
    } catch {
        /* ignore */
    }
    return defaults;
}

export const FILTER_FIELD_DEFS = [
    { key: 'status', label: 'Trạng thái', type: 'enum', optionsKey: 'taskStatus' },
    { key: 'priority', label: 'Ưu tiên', type: 'enum', optionsKey: 'taskPriority' },
    { key: 'phase', label: 'Giai đoạn', type: 'enum', optionsKey: 'taskPhase' },
    { key: 'assignee', label: 'Người phụ trách', type: 'person' },
    { key: 'reviewer', label: 'Người duyệt', type: 'person' },
    { key: 'sprint', label: 'Sprint', type: 'sprint' },
    { key: 'estimate_hours', label: 'Giờ ước tính', type: 'number' },
    { key: 'progress', label: 'Tiến độ (%)', type: 'number' },
    { key: 'due_date', label: 'Hạn xử lý', type: 'date' },
    { key: 'start_date', label: 'Ngày bắt đầu', type: 'date' },
    { key: 'title', label: 'Tiêu đề', type: 'text' },
    { key: 'is_milestone', label: 'Milestone', type: 'boolean' },
];

export const FILTER_OPERATORS = {
    text: [
        { value: 'eq', label: 'Bằng' },
        { value: 'neq', label: 'Khác' },
        { value: 'contains', label: 'Chứa' },
        { value: 'not_contains', label: 'Không chứa' },
        { value: 'empty', label: 'Trống' },
        { value: 'not_empty', label: 'Không trống' },
    ],
    enum: [
        { value: 'eq', label: 'Bằng' },
        { value: 'neq', label: 'Khác' },
        { value: 'empty', label: 'Trống' },
        { value: 'not_empty', label: 'Không trống' },
    ],
    number: [
        { value: 'eq', label: 'Bằng' },
        { value: 'neq', label: 'Khác' },
        { value: 'gt', label: 'Lớn hơn' },
        { value: 'lt', label: 'Nhỏ hơn' },
        { value: 'between', label: 'Trong khoảng' },
        { value: 'empty', label: 'Trống' },
        { value: 'not_empty', label: 'Không trống' },
    ],
    date: [
        { value: 'eq', label: 'Bằng' },
        { value: 'neq', label: 'Khác' },
        { value: 'gt', label: 'Sau ngày' },
        { value: 'lt', label: 'Trước ngày' },
        { value: 'between', label: 'Trong khoảng' },
        { value: 'empty', label: 'Trống' },
        { value: 'not_empty', label: 'Không trống' },
    ],
    person: [
        { value: 'eq', label: 'Bằng' },
        { value: 'neq', label: 'Khác' },
        { value: 'empty', label: 'Trống' },
        { value: 'not_empty', label: 'Không trống' },
    ],
    sprint: [
        { value: 'eq', label: 'Bằng' },
        { value: 'neq', label: 'Khác' },
        { value: 'empty', label: 'Backlog' },
        { value: 'not_empty', label: 'Có Sprint' },
    ],
    boolean: [
        { value: 'eq', label: 'Bằng' },
    ],
};
