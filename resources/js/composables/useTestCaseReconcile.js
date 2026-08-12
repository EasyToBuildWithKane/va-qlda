/**
 * useTestCaseReconcile — pure quality-check composable for test cases.
 *
 * Returns issues[] + summary from a list of test cases (no API calls).
 * Each issue: { level: 'error'|'warning'|'info', code, message, testCaseId? }
 */

const STALE_DAYS = 30;

function daysSince(dateStr) {
    if (!dateStr) return null;
    const diff = Date.now() - new Date(dateStr).getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24));
}

const CHECKS = [
    {
        code: 'ready_without_steps',
        level: 'warning',
        message: (tc) =>
            `"${tc.title}" trạng thái Sẵn sàng nhưng chưa có bước kiểm thử nào.`,
        test: (tc) =>
            tc.status?.value === 'ready' && (!tc.steps || tc.steps.length === 0),
    },
    {
        code: 'fail_without_blocker',
        level: 'error',
        message: (tc) =>
            `"${tc.title}" kết quả Không đạt nhưng chưa liên kết vướng mắc nào.`,
        test: (tc) =>
            tc.last_result?.value === 'fail' && !tc.blocker_id,
    },
    {
        code: 'draft_stale',
        level: 'warning',
        message: (tc) => {
            const d = daysSince(tc.updated_at);
            return `"${tc.title}" ở trạng thái Nháp đã ${d} ngày — cần cập nhật hoặc chuyển Sẵn sàng.`;
        },
        test: (tc) =>
            tc.status?.value === 'draft' && daysSince(tc.updated_at) >= STALE_DAYS,
    },
    {
        code: 'no_owner',
        level: 'info',
        message: (tc) => `"${tc.title}" chưa có người phụ trách.`,
        test: (tc) => !tc.owner_id && !tc.owner,
    },
    {
        code: 'no_expected_result',
        level: 'warning',
        message: (tc) =>
            `"${tc.title}" chưa có kết quả mong đợi — khó đánh giá khi thực thi.`,
        test: (tc) =>
            tc.status?.value === 'ready' && !tc.expected_result,
    },
    {
        code: 'deprecated_with_runs',
        level: 'info',
        message: (tc) =>
            `"${tc.title}" đã Không còn dùng nhưng vẫn có kết quả thực thi gần đây.`,
        test: (tc) =>
            tc.status?.value === 'deprecated' && tc.last_result?.value,
    },
];

/**
 * @param {object[]} testCases
 * @returns {{ issues: object[], summary: { total: number, errors: number, warnings: number, info: number } }}
 */
export function reconcileTestCases(testCases) {
    const issues = [];

    testCases.forEach((tc) => {
        CHECKS.forEach(({ code, level, message, test }) => {
            if (test(tc)) {
                issues.push({
                    level,
                    code,
                    message: message(tc),
                    testCaseId: tc.id,
                    testCaseTitle: tc.title,
                });
            }
        });
    });

    const summary = {
        total: issues.length,
        errors: issues.filter((i) => i.level === 'error').length,
        warnings: issues.filter((i) => i.level === 'warning').length,
        info: issues.filter((i) => i.level === 'info').length,
    };

    return { issues, summary };
}

export const useTestCaseReconcile = () => ({ reconcileTestCases });
