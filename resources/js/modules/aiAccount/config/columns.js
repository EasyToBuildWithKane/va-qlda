/** Cột bảng tài khoản AI (tool + actions luôn hiển thị). */
export const AI_ACCOUNT_COLUMNS = [
    { key: 'license', label: 'License' },
    { key: 'email', label: 'Email đăng ký' },
    { key: 'expiry', label: 'Hết hạn' },
    { key: 'cost', label: 'Chi phí' },
    { key: 'status', label: 'Trạng thái' },
];

export const AI_ACCOUNT_COLUMNS_DEFAULT = ['license', 'email', 'expiry', 'cost', 'status'];

export const AI_ACCOUNT_STATUS_FILTER_OPTS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'active', label: 'Hoạt động' },
    { key: 'expiring_soon', label: 'Sắp hết hạn' },
    { key: 'expired', label: 'Hết hạn' },
    { key: 'cancelled', label: 'Đã huỷ' },
];
