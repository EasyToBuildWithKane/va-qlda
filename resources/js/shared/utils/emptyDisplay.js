/** Ký tự placeholder cấm hiển thị trực tiếp trên UI user-facing (dùng nhãn tiếng Việt). */
export const FORBIDDEN_EMPTY_MARK = '—';

const PLACEHOLDER_MARKS = new Set([FORBIDDEN_EMPTY_MARK, '-', '–', '—', 'N/A', 'n/a']);

export function isEmptyDisplayValue(value) {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') {
        const t = value.trim();
        return t === '' || PLACEHOLDER_MARKS.has(t);
    }
    return false;
}

/** @param {unknown} value @param {string} emptyLabel */
export function displayOrEmpty(value, emptyLabel) {
    return isEmptyDisplayValue(value) ? emptyLabel : value;
}

export const EMPTY_LABELS = {
    team: 'Chưa gán đơn vị',
    role: 'Chưa cập nhật vai trò',
    grade: 'Chưa có xếp loại',
    gradeNoCommitment: 'Chưa có cam kết',
    period: 'Chưa chọn kỳ',
    generic: 'Chưa có dữ liệu',
    notUpdated: 'Chưa cập nhật',
};

export function auditGradeLabel(grade, hasCommitment) {
    if (!hasCommitment) return EMPTY_LABELS.gradeNoCommitment;
    return displayOrEmpty(grade, EMPTY_LABELS.grade);
}
