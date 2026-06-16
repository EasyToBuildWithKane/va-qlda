/** Cột bảng danh sách `/credentials` — đồng bộ `Pages/Credential/Index.vue`. */
export const CREDENTIAL_TABLE_COLUMNS = [
    { key: 'name', label: 'Tên' },
    { key: 'type_system', label: 'Loại / Hệ thống' },
    { key: 'username', label: 'Username' },
    { key: 'owner', label: 'Phụ trách' },
    { key: 'expires_at', label: 'Hết hạn', default: false },
    { key: 'status', label: 'Trạng thái' },
];
