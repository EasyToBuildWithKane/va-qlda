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

/** @deprecated Dùng GROUP_COST_COLUMNS — giữ export tránh break import cũ */
export {
    GROUP_COST_COLUMNS as COST_REPORT_GROUP_COLUMNS,
    GROUP_COST_COLUMNS_DEFAULT as COST_REPORT_GROUP_COLUMNS_DEFAULT,
} from '@/modules/aiAccount/config/groupCostColumns';
