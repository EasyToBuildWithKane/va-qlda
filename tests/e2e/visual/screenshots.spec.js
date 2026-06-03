import { test, expect } from '@playwright/test';
import { postLogin } from '../helpers/loginPost.js';

/**
 * Visual regression baseline (TD-020).
 * Update snapshots: npm run test:e2e:visual -- --update-snapshots
 */
test.describe('Visual regression', () => {
    test('login page', async ({ page }) => {
        await page.goto('/login');
        await expect(page).toHaveScreenshot('login.png', { fullPage: true, maxDiffPixelRatio: 0.02 });
    });

    test('dashboard after member login', async ({ page }) => {
        const response = await postLogin(page, { username: 'member', password: 'password' });
        expect(response.status(), 'E2E login — bật AUTH_PASSWORD_LOGIN').toBeLessThan(400);

        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveScreenshot('dashboard-member.png', { fullPage: true, maxDiffPixelRatio: 0.02 });
    });

    test('projects list after admin login', async ({ page }) => {
        const response = await postLogin(page, { username: 'admin', password: 'password' });
        expect(response.status(), 'E2E login — bật AUTH_PASSWORD_LOGIN').toBeLessThan(400);

        await page.goto('/projects');
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveScreenshot('projects-index.png', { fullPage: true, maxDiffPixelRatio: 0.02 });
    });
});
