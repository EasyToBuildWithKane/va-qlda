import { test, expect } from './helpers/auth.js';

test.describe('Daily Report', () => {
    test('member can view today report page', async ({ page }) => {
        await page.goto('/daily-reports/today');

        await expect(page).toHaveURL(/\/daily-reports\/today/);
        await expect(page.getByRole('heading', { name: /báo cáo hôm nay/i })).toBeVisible();
    });

    test('member can view daily report history', async ({ page }) => {
        await page.goto('/daily-reports');

        await expect(page).toHaveURL(/\/daily-reports/);
        await expect(page.getByRole('heading', { name: /lịch sử báo cáo/i })).toBeVisible();
    });

    test.describe('as lead', () => {
        test.use({ role: 'lead' });

        test('can view review page', async ({ page }) => {
            await page.goto('/daily-reports/review');

            await expect(page).toHaveURL(/\/daily-reports\/review/);
            await expect(page.getByRole('heading', { name: /duyệt báo cáo/i })).toBeVisible();
        });
    });

    test('today page shows report editor', async ({ page }) => {
        await page.goto('/daily-reports/today');

        await expect(
            page.getByRole('button', { name: /lưu nháp/i }).or(page.getByText(/đã nộp báo cáo hôm nay/i)),
        ).toBeVisible();
    });

    test('guest is redirected from daily reports', async ({ browser }) => {
        const ctx = await browser.newContext();
        const page = await ctx.newPage();

        await page.goto('/daily-reports');
        await expect(page).toHaveURL(/\/login/);

        await ctx.close();
    });
});
