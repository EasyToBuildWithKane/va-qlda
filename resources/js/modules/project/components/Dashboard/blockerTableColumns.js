/** Toggleable columns for ProjectBlockerPanel (code + title are always shown). */

export const BLOCKER_TABLE_COLUMNS = [
    { key: 'severity', label: 'Mức độ', sortKey: 'severity' },
    { key: 'status', label: 'Trạng thái', sortKey: 'status' },
    { key: 'owner', label: 'Người phụ trách', sortKey: 'owner' },
    { key: 'raised_by', label: 'Người ghi nhận', sortKey: 'raised_by' },
    { key: 'raised_at', label: 'Ngày phát hiện', sortKey: 'raised_at' },
    { key: 'due_date', label: 'Hạn xử lý', sortKey: 'due_date' },
    { key: 'root_cause', label: 'Nguyên nhân' },
    { key: 'resolution', label: 'Hướng xử lý' },
    { key: 'updated_at', label: 'Cập nhật', sortKey: 'updated_at' },
];

export const BLOCKER_TABLE_DEFAULT_VISIBLE = [
    'severity',
    'status',
    'owner',
    'due_date',
];

export const BLOCKER_TABLE_COLS_KEY = 'va-workspace.blocker-table.columns';

export function loadBlockerTableColumns() {
    try {
        const saved = JSON.parse(localStorage.getItem(BLOCKER_TABLE_COLS_KEY));
        if (Array.isArray(saved) && saved.length) {
            return saved.filter((k) => BLOCKER_TABLE_COLUMNS.some((c) => c.key === k));
        }
    } catch {
        /* ignore */
    }
    return [...BLOCKER_TABLE_DEFAULT_VISIBLE];
}

/** @deprecated Đặt bí danh cho khả năng tương thích ngược một phiên bản */
export const RISK_TABLE_COLUMNS = BLOCKER_TABLE_COLUMNS;
export const RISK_TABLE_DEFAULT_VISIBLE = BLOCKER_TABLE_DEFAULT_VISIBLE;
export const RISK_TABLE_COLS_KEY = BLOCKER_TABLE_COLS_KEY;
export const loadRiskTableColumns = loadBlockerTableColumns;
