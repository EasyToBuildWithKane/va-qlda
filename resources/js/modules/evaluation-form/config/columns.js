export const EVALUATION_FORM_TABLE_COLUMNS = [
    { key: 'name', label: 'Tên phiếu đánh giá', default: true },
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'criteria_count', label: 'Số tiêu chí', default: true },
    { key: 'assignees_count', label: 'Số nhân sự', default: true },
    { key: 'created_at', label: 'Ngày tạo', default: true },
    { key: 'creator', label: 'Người tạo', default: true },
    { key: 'form_code', label: 'Mã phiếu', default: false },
    { key: 'period', label: 'Kỳ đánh giá', default: false },
    { key: 'deadline', label: 'Hạn đánh giá', default: false },
    { key: 'type', label: 'Loại đánh giá', default: false },
    { key: 'template', label: 'Mẫu đánh giá', default: false },
];
