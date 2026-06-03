/** Cột bảng đề xuất mua (công cụ + thao tác luôn hiển thị). */
export const PROPOSAL_COLUMNS = [
    { key: 'license', label: 'License' },
    { key: 'cost', label: 'Chi phí dự kiến' },
    { key: 'status', label: 'Trạng thái duyệt' },
    { key: 'sender', label: 'Người gửi' },
    { key: 'reject_reason', label: 'Lý do từ chối' },
    { key: 'review_notes', label: 'Ghi chú sau duyệt' },
];

export const PROPOSAL_COLUMNS_DEFAULT = ['license', 'cost', 'status', 'sender'];

export const COST_REPORT_GROUP_COLUMNS = [
    { key: 'counts', label: 'Số lượng (Active / cảnh báo)' },
    { key: 'cost_active', label: 'CP active/tháng' },
    { key: 'cost_all', label: 'CP tất cả/tháng' },
    { key: 'share', label: 'Tỷ trọng' },
];

export const COST_REPORT_GROUP_COLUMNS_DEFAULT = ['counts', 'cost_active', 'cost_all', 'share'];
