/** Cột bảng Cấu hình đánh giá — dùng với useVisibleColumns.
 * Phòng ban không còn là cột: nhóm collapse theo phòng (pattern Blocker). */
export const EVALUATION_TABLE_COLUMNS = [
    { key: 'template_type', label: 'Loại' },
    { key: 'effective', label: 'Hiệu lực' },
    { key: 'effective_from', label: 'Từ ngày', default: false },
    { key: 'effective_to', label: 'Đến ngày', default: false },
    { key: 'criteria_count', label: 'Tiêu chí' },
    { key: 'base_score', label: 'Điểm gốc', default: false },
    { key: 'description', label: 'Mô tả', default: false },
    { key: 'creator', label: 'Người tạo', default: false },
    { key: 'created_at', label: 'Ngày tạo', default: false },
    { key: 'updated_at', label: 'Cập nhật', default: false },
    { key: 'status', label: 'Trạng thái' },
];
