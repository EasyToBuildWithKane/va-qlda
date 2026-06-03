/** Cột bảng tài khoản AI (tool + actions luôn hiển thị). */
export const AI_ACCOUNT_COLUMNS = [
    { key: 'license', label: 'License' },
    { key: 'email', label: 'Email đăng ký' },
    { key: 'expiry', label: 'Hết hạn' },
    { key: 'cost', label: 'Chi phí' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'payment', label: 'Thanh toán GH' },
];

export const AI_ACCOUNT_COLUMNS_DEFAULT = ['license', 'email', 'expiry', 'cost', 'status', 'payment'];

export const AI_ACCOUNT_STATUS_FILTER_OPTS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'active', label: 'Hoạt động' },
    { key: 'expiring_soon', label: 'Sắp hết hạn' },
    { key: 'expired', label: 'Hết hạn' },
    { key: 'cancelled', label: 'Đã huỷ' },
];

/** Checkbox «Lọc» — chọn control hiển thị trên dòng 2 (không phải giá trị lọc). */
export const AI_ACCOUNT_RENEWAL_PAYMENT_FILTER_OPTS = [
    { key: 'all', label: 'Tất cả cần TT' },
    { key: 'unpaid', label: 'Chưa thanh toán GH' },
    { key: 'paid', label: 'Đã thanh toán GH' },
    { key: 'due', label: 'Cần ghi nhận TT (sắp/hết hạn)' },
];

export const AI_ACCOUNT_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'renewal_payment', label: 'Thanh toán gia hạn', default: true },
    { key: 'group', label: 'Nhóm chức năng', default: true },
    { key: 'attention', label: 'Cần chú ý (sắp/hết hạn)', default: false },
];

export const AI_ACCOUNT_TABLE_COLUMNS = AI_ACCOUNT_COLUMNS.map((c) => ({
    ...c,
    default: AI_ACCOUNT_COLUMNS_DEFAULT.includes(c.key),
}));
