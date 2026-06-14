import { test, expect } from './helpers/auth.js';

test.describe('Blockers (Vướng mắc)', () => {
    test('member can view blocker list', async ({ page }) => {
        await page.goto('/blockers');

        await expect(page).toHaveURL(/\/blockers/);
        await expect(page.getByRole('heading', { name: /vướng mắc/i })).toBeVisible();
    });

    test.describe('as viewer', () => {
        test.use({ role: 'viewer' });

        test('can view blocker list', async ({ page }) => {
            await page.goto('/blockers');

            await expect(page).toHaveURL(/\/blockers/);
        });

        test('does not see create button', async ({ page }) => {
            await page.goto('/blockers');

            await expect(page.getByRole('button', { name: 'Ghi nhận vướng mắc' })).toHaveCount(0);
        });
    });

    test('member can open create blocker form', async ({ page }) => {
        await page.goto('/blockers');

        await page.getByRole('button', { name: 'Ghi nhận vướng mắc' }).click();
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
