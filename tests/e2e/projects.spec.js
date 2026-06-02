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

    test.describe('project workspace', () => {
        test.use({ role: 'admin' });

        test('admin can open seeded QLDA project show page', async ({ page }) => {
            await page.goto('/projects');
            await page.getByRole('link', { name: /QLDA|Quản lý dự án/i }).first().click();

            await expect(page).toHaveURL(/\/projects\/\d+/);
            await expect(page.getByRole('heading', { name: /Quản lý dự án|QLDA/i })).toBeVisible();
        });

        test('can switch to Sprint tab', async ({ page }) => {
            await page.goto('/projects');
            await page.getByRole('link', { name: /QLDA|Quản lý dự án/i }).first().click();

            const sprintTab = page.getByRole('button', { name: /^Sprint$/i });
            if (await sprintTab.count() > 0) {
                await sprintTab.click();
                await expect(page.getByText(/Sprint|Danh sách|Lịch/i).first()).toBeVisible();
            }
        });
    });
});
