import { test as base } from '@playwright/test';
import { postLogin } from './loginPost.js';

/**
 * Programmatic login (POST /login) for E2E — not shown on production login UI.
 * Requires AUTH_PASSWORD_LOGIN enabled (default outside production).
 */
async function loginAs(page, role) {
    const response = await postLogin(page, {
        username: role,
        password: 'password',
    });

    if (response.status() >= 400) {
        throw new Error(`E2E login failed for role "${role}" (HTTP ${response.status()}). Enable AUTH_PASSWORD_LOGIN for local/E2E.`);
    }

    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');
}

export const test = base.extend({
    role: ['member', { option: true }],

    page: async ({ page, role }, use) => {
        await loginAs(page, role);
        await use(page);
    },
});

export { expect } from '@playwright/test';
