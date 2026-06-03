/** Chi phí ngân sách theo phiếu đã duyệt (khớp backend). */
export function budgetMonthly(account) {
    if (!account) return 0;
    if (account.budget_cost_monthly != null) {
        return account.budget_cost_monthly;
    }
    return account.cost_in_budget ? (account.cost_monthly ?? 0) : 0;
}
