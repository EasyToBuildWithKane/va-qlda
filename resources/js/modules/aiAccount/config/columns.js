/** Cột bảng tài khoản AI (tool + actions luôn hiển thị). */
export const AI_ACCOUNT_COLUMNS = [
    { key: 'email', label: 'Email đăng ký' },
    { key: 'purchase', label: 'Ngày mua' },
    { key: 'expiry', label: 'Ngày hết hạn' },
    { key: 'purchase_type', label: 'Loại mua' },
    { key: 'proposal_approved', label: 'Duyệt PĐX' },
    { key: 'proposal_sent', label: 'Ngày gửi PĐX' },
    { key: 'payment_sent', label: 'Ngày gửi ĐNTT' },
    { key: 'cost', label: 'Chi phí' },
    { key: 'status', label: 'Trạng thái' },
];

export const AI_ACCOUNT_COLUMNS_DEFAULT = [
    'email', 'purchase', 'expiry', 'purchase_type', 'proposal_approved', 'cost', 'status',
];

export const AI_ACCOUNT_STATUS_FILTER_OPTS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'active', label: 'Hoạt động' },
    { key: 'expiring_soon', label: 'Sắp hết hạn' },
    { key: 'expired', label: 'Hết hạn' },
    { key: 'cancelled', label: 'Đã huỷ' },
];

export const AI_ACCOUNT_PURCHASE_TYPE_FILTER_OPTS = [
    { key: 'all', label: 'Tất cả loại mua' },
    { key: 'new', label: 'Mua mới' },
    { key: 'renewal', label: 'Gia hạn' },
];

export const AI_ACCOUNT_APPROVAL_FILTER_OPTS = [
    { key: 'all', label: 'Tất cả duyệt PĐX' },
    { key: 'approved', label: 'Đã duyệt' },
    { key: 'pending', label: 'Chưa duyệt' },
];

export const AI_ACCOUNT_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'purchase_type', label: 'Loại mua', default: false },
    { key: 'proposal_approved', label: 'Duyệt PĐX', default: false },
    { key: 'group', label: 'Nhóm chức năng', default: false },
    { key: 'attention', label: 'Cần chú ý (sắp/hết hạn)', default: false },
];

export const AI_ACCOUNT_TABLE_COLUMNS = AI_ACCOUNT_COLUMNS.map((c) => ({
    ...c,
    default: AI_ACCOUNT_COLUMNS_DEFAULT.includes(c.key),
}));
