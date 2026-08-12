import { test, expect } from './helpers/auth.js';

test.describe('QA / Test case', () => {
    test('member can view test case list', async ({ page }) => {
        await page.goto('/test-cases');

        await expect(page).toHaveURL(/\/test-cases/);
        await expect(page.getByRole('heading', { name: /QA \/ Test case/i })).toBeVisible();
    });

    test.describe('as viewer', () => {
        test.use({ role: 'viewer' });

        test('can view test case list', async ({ page }) => {
            await page.goto('/test-cases');

            await expect(page).toHaveURL(/\/test-cases/);
        });

        test('does not see create button', async ({ page }) => {
            await page.goto('/test-cases');

            await expect(page.getByRole('button', { name: 'Thêm test case' })).toHaveCount(0);
        });
    });

    test('guest is redirected from test-cases', async ({ browser }) => {
        const ctx = await browser.newContext();
        const page = await ctx.newPage();

        await page.goto('/test-cases');
        await expect(page).toHaveURL(/\/login/);

        await ctx.close();
    });

    test('kpi strip is visible on test case index', async ({ page }) => {
        await page.goto('/test-cases');

        await expect(page.getByText('Tổng quan QA / Test case')).toBeVisible();
    });

    test('data toolbar button is visible', async ({ page }) => {
        await page.goto('/test-cases');

        await expect(page.getByRole('button', { name: /Dữ liệu/i })).toBeVisible();
    });
});
