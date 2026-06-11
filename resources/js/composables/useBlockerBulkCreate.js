import {
    BULK_MAX_ROWS,
    bulkValidationSummary,
    nextBulkRowId,
    normalizeBulkLine,
    parseBulkText,
    rowsToBulkText,
    validateBulkRows,
} from '@/composables/useTaskBulkCreate';

export {
    BULK_MAX_ROWS,
    bulkValidationSummary,
    nextBulkRowId,
    normalizeBulkLine,
    parseBulkText,
    rowsToBulkText,
};

const SAMPLE_LINES = [
    'API đăng nhập trả lỗi 500 khi tải cao',
    'Chưa có quyền truy cập môi trường staging',
    'Thiếu tài liệu nghiệp vụ từ phía khách hàng',
];

export function getBlockerBulkSampleText() {
    return SAMPLE_LINES.join('\n');
}

/** @param {import('@/composables/useTaskBulkCreate').BulkRow[]} rows */
export function validateBlockerBulkRows(rows) {
    return validateBulkRows(rows, []);
}
