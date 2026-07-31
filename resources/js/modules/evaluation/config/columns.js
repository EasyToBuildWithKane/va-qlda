/** Cột bảng tiêu chí đánh giá — dùng với useVisibleColumns.
 * Phòng ban: nhóm collapse; loại tiêu chí: tab lọc trong từng nhóm (không dòng nhóm loại).
 * Cột mức điểm (nhãn / mô tả / +điểm) render động theo `score_levels`, không nằm trong danh sách này. */
export const EVALUATION_TABLE_COLUMNS = [
    { key: 'allow_half_score', label: 'Chấm 0.5', default: false },
    { key: 'description', label: 'Mô tả', default: false },
    { key: 'creator', label: 'Người tạo', default: false },
    { key: 'created_at', label: 'Ngày tạo', default: false },
    { key: 'updated_at', label: 'Cập nhật', default: false },
    { key: 'status', label: 'Trạng thái' },
];
