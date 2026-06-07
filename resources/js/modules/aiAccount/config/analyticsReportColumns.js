/** @typedef {{ key: string, label: string, default?: boolean, groupable?: boolean, width?: string }} AnalyticsColumn */

/** @type {AnalyticsColumn[]} */
export const ANALYTICS_REPORT_COLUMNS = [
    { key: 'proposal_code', label: 'Mã hồ sơ', default: true, width: '8rem' },
    { key: 'tool_name', label: 'Tên sản phẩm AI', default: true, groupable: true },
    { key: 'vendor_name', label: 'Nhà cung cấp', default: true, groupable: true },
    { key: 'license_type', label: 'Loại gói', default: false },
    { key: 'user_name', label: 'Người sử dụng', default: true, groupable: true },
    { key: 'department', label: 'Phòng ban', default: true, groupable: true },
    { key: 'unit', label: 'Đơn vị', default: true, groupable: true },
    { key: 'registration_date', label: 'Ngày đăng ký', default: false },
    { key: 'purchase_date', label: 'Ngày mua', default: true },
    { key: 'activated_at', label: 'Ngày kích hoạt', default: false },
    { key: 'expiry_date', label: 'Ngày hết hạn', default: true },
    { key: 'months_used', label: 'Số tháng sử dụng', default: false },
    { key: 'cost_monthly', label: 'Chi phí tháng', default: true },
    { key: 'cost_yearly', label: 'Chi phí năm', default: false },
    { key: 'actual_cost', label: 'Chi phí thực tế', default: true },
    { key: 'status_label', label: 'Trạng thái', default: true, groupable: true },
    { key: 'approver_name', label: 'Người duyệt', default: false },
    { key: 'notes', label: 'Ghi chú', default: false },
];

export const DEFAULT_VISIBLE_ANALYTICS_COLUMNS = ANALYTICS_REPORT_COLUMNS
    .filter((c) => c.default)
    .map((c) => c.key);
