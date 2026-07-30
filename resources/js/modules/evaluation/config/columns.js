/** Cột bảng tiêu chí đánh giá — dùng với useVisibleColumns.
 * Phòng ban / phạm vi: nhóm collapse (chung + theo PB). */
export const EVALUATION_TABLE_COLUMNS = [
    { key: 'criteria_code', label: 'Mã' },
    { key: 'category', label: 'Loại' },
    { key: 'allow_half_score', label: 'Chấm 0.5', default: false },
    { key: 'description', label: 'Mô tả', default: false },
    { key: 'creator', label: 'Người tạo', default: false },
    { key: 'created_at', label: 'Ngày tạo', default: false },
    { key: 'updated_at', label: 'Cập nhật', default: false },
    { key: 'status', label: 'Trạng thái' },
];
