// Single source of truth for the project DataGrid columns.
// `name` is always shown (sticky first column) and is not part of the toggle set.

export const COLUMNS = [
    { key: 'code', label: 'Mã dự án', align: 'left', sortable: true, colClass: 'min-w-[6rem]' },
    { key: 'type', label: 'Loại dự án', align: 'left', sortable: true, colClass: 'min-w-[7.5rem]' },
    { key: 'category', label: 'Phân loại', align: 'left', sortable: true, colClass: 'min-w-[7rem]' },
    { key: 'scope', label: 'Phạm vi', align: 'left', sortable: true, colClass: 'min-w-[7rem]' },
    { key: 'department', label: 'Phòng ban', align: 'left', sortable: true, colClass: 'min-w-[11rem]' },
    { key: 'manager', label: 'Chủ dự án', align: 'left', sortable: true, colClass: 'min-w-[9.5rem]' },
    { key: 'status', label: 'Trạng thái', align: 'left', sortable: true, colClass: 'min-w-[7rem]' },
    { key: 'progress', label: 'Tiến độ %', align: 'center', sortable: true, colClass: 'min-w-[4.5rem]' },
    { key: 'start_date', label: 'Ngày bắt đầu', align: 'left', sortable: true, colClass: 'min-w-[6.5rem]' },
    { key: 'due_date', label: 'Ngày kết thúc', align: 'left', sortable: true, colClass: 'min-w-[6.5rem]' },
    { key: 'created_at', label: 'Ngày tạo', align: 'left', sortable: true, colClass: 'min-w-[6rem]' },
    { key: 'updated_at', label: 'Cập nhật', align: 'left', sortable: true, colClass: 'min-w-[6rem]' },
    { key: 'task_count', label: 'Công việc', align: 'center', sortable: true, colClass: 'min-w-[4.5rem]' },
    { key: 'member_count', label: 'Thành viên', align: 'left', sortable: true, colClass: 'min-w-[11rem]' },
    { key: 'open_blocker_count', label: 'Khúc mắc', align: 'center', sortable: true, colClass: 'min-w-[4.5rem]' },
];

export const DEFAULT_VISIBLE = [
    'type', 'department', 'manager', 'status', 'progress', 'due_date', 'task_count', 'member_count',
];

/** A sortable / exportable primitive for a given project + column key. */
export function cellValue(p, key) {
    switch (key) {
        case 'type': return p.type?.label ?? '';
        case 'category': return p.category?.label ?? '';
        case 'scope': return p.scope?.label ?? '';
        case 'status': return p.status?.label ?? '';
        case 'department': {
            const names = [p.department?.name, ...(p.related_departments || []).map((d) => d.name)].filter(Boolean);
            return names.join(', ');
        }
        case 'manager': return p.manager?.name ?? '';
        case 'progress': return p.progress ?? 0;
        case 'task_count': return p.task_count ?? 0;
        case 'member_count': return p.member_count ?? 0;
        case 'open_blocker_count': return p.open_blocker_count ?? 0;
        default: return p[key] ?? '';
    }
}
