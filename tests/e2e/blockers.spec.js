import { test, expect } from './helpers/auth.js';

test.describe('Blockers (Test case)', () => {
    test('member can view blocker list', async ({ page }) => {
        await page.goto('/blockers');

        await expect(page).toHaveURL(/\/blockers/);
        await expect(page.getByRole('heading', { name: /trường hợp kiểm thử/i })).toBeVisible();
    });

    test.describe('as viewer', () => {
        test.use({ role: 'viewer' });

        test('can view blocker list', async ({ page }) => {
            await page.goto('/blockers');

            await expect(page).toHaveURL(/\/blockers/);
        });

        test('does not see create button', async ({ page }) => {
            await page.goto('/blockers');

            await expect(page.getByRole('button', { name: 'Ghi nhận test case' })).toHaveCount(0);
        });
    });

    test('member can open create blocker form', async ({ page }) => {
        await page.goto('/blockers');

        await page.getByRole('button', { name: 'Ghi nhận test case' }).click();
        await expect(page.getByRole('dialog').or(page.locator('form'))).toBeVisible();
    });

    test('guest is redirected from blockers', async ({ browser }) => {
        const ctx = await browser.newContext();
        const page = await ctx.newPage();

        await page.goto('/blockers');
        await expect(page).toHaveURL(/\/login/);

        await ctx.close();
    });
});
