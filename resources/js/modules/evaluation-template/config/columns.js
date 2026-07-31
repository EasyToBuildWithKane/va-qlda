/** Cột bảng mẫu đánh giá — dùng với useVisibleColumns. */
export const EVALUATION_TEMPLATE_TABLE_COLUMNS = [
    { key: 'criteria', label: 'Tiêu chí đánh giá', default: true },
    { key: 'criteria_count', label: 'Số tiêu chí', default: true },
    { key: 'position', label: 'Vị trí đánh giá', default: true },
    { key: 'creator', label: 'Người tạo', default: true },
    { key: 'created_at', label: 'Ngày tạo', default: true },
    { key: 'updated_at', label: 'Ngày cập nhật', default: true },
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'description', label: 'Mô tả', default: false },
];

/** Cột có thể chọn khi xuất Excel. */
export const TEMPLATE_EXPORT_COLUMN_OPTIONS = [
    { key: 'template_code', label: 'Mã mẫu đánh giá', core: true },
    { key: 'name', label: 'Tên mẫu đánh giá', core: true },
    { key: 'position', label: 'Vị trí đánh giá', core: true },
    { key: 'criteria_count', label: 'Số tiêu chí', core: true },
    { key: 'criteria', label: 'Tiêu chí đánh giá', core: true },
    { key: 'creator', label: 'Người tạo' },
    { key: 'created_at', label: 'Ngày tạo' },
    { key: 'updated_at', label: 'Ngày cập nhật' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'description', label: 'Mô tả' },
];
