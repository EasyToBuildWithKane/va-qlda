import { test, expect } from '@playwright/test';

/**
 * Visual regression baseline (TD-020).
 * Update snapshots: npx playwright test tests/e2e/visual --update-snapshots
 */
test.describe('Visual regression', () => {
    test('login page', async ({ page }) => {
        await page.goto('/login');
        await expect(page).toHaveScreenshot('login.png', { fullPage: true, maxDiffPixelRatio: 0.02 });
    });

    test('dashboard after member login', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username').fill('member');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();
        await page.waitForURL(/\/dashboard/);
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveScreenshot('dashboard-member.png', { fullPage: true });
    });

    test('projects list after admin login', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username').fill('admin');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();
        await page.waitForURL(/\/dashboard/);

        await page.goto('/projects');
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveScreenshot('projects-index.png', { fullPage: true });
    });
});
