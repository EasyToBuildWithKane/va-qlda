/** Chi phí quy tháng từ tài khoản. */
export function budgetMonthly(account) {
    if (!account) return 0;
    return account.cost_monthly ?? 0;
}
