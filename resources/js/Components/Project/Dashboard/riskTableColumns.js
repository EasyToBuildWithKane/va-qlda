/** Toggleable columns for RiskIssueDataTable (code + title are always shown). */

export const RISK_TABLE_COLUMNS = [
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

export const RISK_TABLE_DEFAULT_VISIBLE = [
    'severity',
    'status',
    'owner',
    'raised_by',
    'due_date',
];

export const RISK_TABLE_COLS_KEY = 'va-qlda.risk-table.columns';

export function loadRiskTableColumns() {
    try {
        const saved = JSON.parse(localStorage.getItem(RISK_TABLE_COLS_KEY));
        if (Array.isArray(saved) && saved.length) {
            return saved.filter((k) => RISK_TABLE_COLUMNS.some((c) => c.key === k));
        }
    } catch {
        /* ignore */
    }
    return [...RISK_TABLE_DEFAULT_VISIBLE];
}
