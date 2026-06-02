import { test, expect } from './helpers/auth.js';

test.describe('Departments (Phòng ban)', () => {
    test('member can view departments page', async ({ page }) => {
        await page.goto('/departments');

        await expect(page).toHaveURL(/\/departments/);
        await expect(page.getByRole('heading', { name: 'Quản lý phòng ban' })).toBeVisible();
    });

    test('member does not see create department button', async ({ page }) => {
        await page.goto('/departments');

        await expect(page.getByRole('button', { name: /thêm phòng ban/i })).toHaveCount(0);
    });

    test.describe('as admin', () => {
        test.use({ role: 'admin' });

        test('can view departments page', async ({ page }) => {
            await page.goto('/departments');

            await expect(page).toHaveURL(/\/departments/);
            await expect(page.getByRole('heading', { name: 'Quản lý phòng ban' })).toBeVisible();
        });

        test('sees create department button', async ({ page }) => {
            await page.goto('/departments');

            await expect(page.getByRole('button', { name: /thêm phòng ban/i })).toBeVisible();
        });

        test('can open add department dialog', async ({ page }) => {
            await page.goto('/departments');

            await page.getByRole('button', { name: /thêm phòng ban/i }).click();
            await expect(page.getByRole('dialog').or(page.locator('form'))).toBeVisible();
        });
    });

    test('guest is redirected from departments', async ({ browser }) => {
        const ctx = await browser.newContext();
        const page = await ctx.newPage();

        await page.goto('/departments');
        await expect(page).toHaveURL(/\/login/);

        await ctx.close();
    });
});
