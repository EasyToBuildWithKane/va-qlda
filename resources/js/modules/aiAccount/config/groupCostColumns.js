/** Cột bảng «Chi phí AI theo nhóm» (cột Nhóm luôn hiển thị). */
export const GROUP_COST_COLUMNS = [
    { key: 'stats', label: 'Thống kê tài khoản', default: true },
    { key: 'cost_monthly', label: 'Chi phí / tháng', default: true },
    { key: 'share', label: 'Tỷ trọng ngân sách', default: true },
    { key: 'yearly', label: 'Ước tính / năm', default: false },
];

export const GROUP_COST_COLUMNS_DEFAULT = GROUP_COST_COLUMNS
    .filter((c) => c.default)
    .map((c) => c.key);
