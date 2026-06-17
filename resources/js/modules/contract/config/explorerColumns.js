/** Cột bảng Explorer — đồng bộ Index (ColumnVisibilityDropdown) và ContractExplorer.vue */
export const CONTRACT_EXPLORER_COLUMNS = [
    { key: 'code', label: 'Mã HĐ' },
    { key: 'vendor', label: 'Nhà cung cấp' },
    { key: 'name', label: 'Tên dịch vụ' },
    { key: 'role', label: 'Loại (gốc / phụ lục)', default: false },
    { key: 'using_unit', label: 'Phòng ban / đơn vị' },
    { key: 'owner', label: 'Phụ trách', default: false },
    { key: 'signed_date', label: 'Ngày ký kết', default: false },
    { key: 'effective_date', label: 'Ngày hiệu lực', default: false },
    { key: 'expiry_date', label: 'Ngày hết hạn' },
    { key: 'days_remaining', label: 'Còn lại' },
    { key: 'monthly_cost', label: 'Chi phí / tháng' },
    { key: 'annual_cost', label: 'Chi phí / năm', default: false },
    { key: 'lifecycle_cost', label: 'Tổng giá trị HĐ' },
    { key: 'payment_status', label: 'Thanh toán', default: false },
    { key: 'billing_cycle', label: 'Chu kỳ thanh toán', default: false },
    { key: 'status', label: 'Trạng thái' },
    { key: 'attachments_count', label: 'Hồ sơ đính kèm', default: false },
];

export const CONTRACT_EXPLORER_COLUMN_KEYS = CONTRACT_EXPLORER_COLUMNS.map((c) => c.key);
