import { test as base } from '@playwright/test';

/**
 * Extend the base test with a `loggedInPage` fixture that signs in as
 * "member" before each test. Use the returned `page` object normally.
 * Override `role` option for admin/lead/viewer flows.
 *
 * Usage:
 *   import { test, expect } from '../helpers/auth.js';
 *   test('my test', async ({ page }) => { ... });
 */
export const test = base.extend({
    role: ['member', { option: true }],

    page: async ({ page, role }, use) => {
        await page.goto('/login');
        await page.getByLabel('Username').fill(role);
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();
        await page.waitForURL(/\/dashboard/);
        await page.waitForLoadState('networkidle');
        await use(page);
    },
});

export { expect } from '@playwright/test';
