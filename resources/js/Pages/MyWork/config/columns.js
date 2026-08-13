/** Cột bảng việc cá nhân / thành viên — dùng với useVisibleColumns. Cột Công việc luôn hiện. */
export const MY_WORK_TABLE_COLUMNS = [
    { key: 'project', label: 'Dự án' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'priority', label: 'Ưu tiên' },
    { key: 'due_date', label: 'Hạn' },
    { key: 'progress', label: 'Tiến độ' },
    { key: 'logged_today', label: 'Giờ hôm nay' },
    { key: 'estimate', label: 'Dự kiến' },
    { key: 'sprint', label: 'Sprint' },
    { key: 'phase', label: 'Giai đoạn', default: false },
    { key: 'source', label: 'Nguồn', default: false },
    { key: 'start_date', label: 'Ngày bắt đầu', default: false },
    { key: 'story_points', label: 'Story points', default: false },
    { key: 'actual_hours', label: 'Giờ thực tế', default: false },
    { key: 'milestone', label: 'Mốc', default: false },
    { key: 'sla', label: 'SLA', default: false },
    { key: 'epic', label: 'Epic', default: false },
    { key: 'parent', label: 'Việc cha', default: false },
    { key: 'assignee', label: 'Người làm', default: false },
    { key: 'reporter', label: 'Người giao', default: false },
    { key: 'reviewer', label: 'Người duyệt', default: false },
];

/** Cột bảng roster nhóm — thành viên + thao tác luôn hiện. */
export const TEAM_ROSTER_COLUMNS = [
    { key: 'org', label: 'Nhóm tổ chức' },
    { key: 'department', label: 'Phòng ban' },
    { key: 'role', label: 'Vai trò' },
    { key: 'open', label: 'Việc mở' },
    { key: 'overdue', label: 'Quá hạn' },
    { key: 'today', label: 'Hôm nay' },
    { key: 'upcoming', label: 'Sắp tới', default: false },
    { key: 'inProgress', label: 'Đang làm' },
    { key: 'noDue', label: 'Chưa có hạn', default: false },
    { key: 'load', label: 'Tải nhóm' },
];
