import { test, expect } from './helpers/auth.js';

test.describe('Bug Tracker', () => {
    test('member can view bug list', async ({ page }) => {
        await page.goto('/bugs');

        await expect(page).toHaveURL(/\/bugs/);
        await expect(page.getByRole('heading', { name: /bug/i })).toBeVisible();
    });

    test.describe('as viewer', () => {
        test.use({ role: 'viewer' });

        test('can view bug list', async ({ page }) => {
            await page.goto('/bugs');

            await expect(page).toHaveURL(/\/bugs/);
        });

        test('does not see create bug button', async ({ page }) => {
            await page.goto('/bugs');

            await expect(page.getByRole('button', { name: /báo lỗi/i })).toHaveCount(0);
        });
    });

    test('member sees create bug button', async ({ page }) => {
        await page.goto('/bugs');

        await expect(page.getByRole('button', { name: /báo lỗi/i })).toBeVisible();
    });

    test('bug list has table', async ({ page }) => {
        await page.goto('/bugs');

        await expect(page.locator('table')).toBeVisible();
    });

    test('guest is redirected from bugs', async ({ browser }) => {
        const ctx = await browser.newContext();
        const page = await ctx.newPage();

        await page.goto('/bugs');
        await expect(page).toHaveURL(/\/login/);

        await ctx.close();
    });
});
