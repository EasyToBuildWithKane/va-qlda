import { test, expect } from './helpers/auth.js';

test.describe('Projects', () => {
    test('member can view project list', async ({ page }) => {
        await page.goto('/projects');

        await expect(page).toHaveURL(/\/projects/);
        await expect(page.getByRole('heading', { name: /dự án/i })).toBeVisible();
    });

    test('project list shows summary cards', async ({ page }) => {
        await page.goto('/projects');

        await expect(page.getByText('Tổng dự án')).toBeVisible();
    });

    test.describe('as admin', () => {
        test.use({ role: 'admin' });

        test('can navigate to create project page', async ({ page }) => {
            await page.goto('/projects/create');

            await expect(page).toHaveURL(/\/projects\/create/);
            await expect(page.getByRole('heading', { name: 'Tạo dự án mới' })).toBeVisible();
        });
    });

    test.describe('as viewer', () => {
        test.use({ role: 'viewer' });

        test('can view project list', async ({ page }) => {
            await page.goto('/projects');

            await expect(page).toHaveURL(/\/projects/);
        });
    });

    test('guest is redirected to login from projects', async ({ browser }) => {
        const ctx = await browser.newContext();
        const page = await ctx.newPage();

        await page.goto('/projects');
        await expect(page).toHaveURL(/\/login/);

        await ctx.close();
    });
});
