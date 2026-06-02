// Single source of truth for the project DataGrid columns. Used by both the
// grid renderer and the "Cột hiển thị" chooser so they never drift apart.
// `name` is always shown (sticky first column) and is not part of the toggle set.

export const COLUMNS = [
    { key: 'code', label: 'Mã dự án', align: 'left', sortable: true },
    { key: 'type', label: 'Loại dự án', align: 'left', sortable: true },
    { key: 'scope', label: 'Phạm vi', align: 'left', sortable: true },
    { key: 'department', label: 'Phòng ban', align: 'left', sortable: true },
    { key: 'manager', label: 'Chủ dự án', align: 'left', sortable: true },
    { key: 'status', label: 'Trạng thái', align: 'left', sortable: true },
    { key: 'progress', label: 'Tiến độ %', align: 'left', sortable: true },
    { key: 'budget', label: 'Ngân sách dự kiến', align: 'right', sortable: true },
    { key: 'actual_budget', label: 'Ngân sách thực tế', align: 'right', sortable: true },
    { key: 'labor_cost', label: 'Chi phí (lương)', align: 'right', sortable: true },
    { key: 'start_date', label: 'Ngày bắt đầu', align: 'left', sortable: true },
    { key: 'due_date', label: 'Ngày kết thúc', align: 'left', sortable: true },
    { key: 'created_at', label: 'Ngày tạo', align: 'left', sortable: true },
    { key: 'updated_at', label: 'Cập nhật', align: 'left', sortable: true },
    { key: 'task_count', label: 'Số công việc', align: 'center', sortable: true },
    { key: 'member_count', label: 'Số thành viên', align: 'center', sortable: true },
    { key: 'open_blocker_count', label: 'Khúc mắc', align: 'center', sortable: true },
];

// Columns shown by default the first time (before any localStorage preference).
export const DEFAULT_VISIBLE = [
    'code', 'type', 'scope', 'department', 'status', 'progress', 'budget', 'labor_cost',
];

/** A sortable / exportable primitive for a given project + column key. */
export function cellValue(p, key) {
    switch (key) {
        case 'type': return p.type?.label ?? '';
        case 'scope': return p.scope?.label ?? '';
        case 'status': return p.status?.label ?? '';
        case 'department': return p.department?.name ?? '';
        case 'manager': return p.manager?.name ?? '';
        case 'progress': return p.progress ?? 0;
        case 'budget': return p.budget ?? 0;
        case 'actual_budget': return p.actual_budget ?? 0;
        case 'labor_cost': return p.labor_cost ?? 0;
        case 'task_count': return p.task_count ?? 0;
        case 'member_count': return p.member_count ?? 0;
        case 'open_blocker_count': return p.open_blocker_count ?? 0;
        default: return p[key] ?? '';
    }
}
