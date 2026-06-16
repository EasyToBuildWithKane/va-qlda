/** @typedef {{ key: string, label: string, default?: boolean }} AuditColumnDef */

/** @type {AuditColumnDef[]} */
export const PERFORMANCE_AUDIT_TABLE_COLUMNS = [
    { key: 'team', label: 'Team' },
    { key: 'period', label: 'Kỳ' },
    { key: 'committed', label: 'Cam kết' },
    { key: 'done', label: 'Hoàn thành' },
    { key: 'commitment_rate', label: 'Tỷ lệ %' },
    { key: 'score', label: 'Điểm' },
    { key: 'grade', label: 'Xếp loại' },
    { key: 'rank', label: 'Hạng', default: false },
];
