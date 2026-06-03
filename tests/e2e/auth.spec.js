import { test, expect } from '@playwright/test';
import { postLogin } from './helpers/loginPost.js';

test.describe('Authentication', () => {
    test('login page shows Google sign-in', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'Đăng nhập', level: 1 })).toBeVisible();
        const googleLink = page.getByRole('link', { name: 'Đăng nhập bằng Google' });
        await expect(googleLink).toBeVisible();
        await expect(page.locator('img[src="/images/google.png"]')).toBeVisible();
        const href = await googleLink.getAttribute('href');
        expect(href === '#' || /\/auth\/google/.test(href ?? '')).toBeTruthy();
    });

    test('member can sign in and reach dashboard', async ({ page }) => {
        const response = await postLogin(page, {
            username: 'member',
            password: 'password',
        });
        expect(response.ok() || response.status() === 302).toBeTruthy();

        await page.goto('/dashboard');
        await expect(page.getByRole('heading', { name: 'Bảng điều khiển' })).toBeVisible();
        await expect(page.getByText('Foundation ready')).toBeVisible();
    });

    test('password login rejects invalid credentials', async ({ page }) => {
        const response = await postLogin(page, {
            username: `invalid-${Date.now()}`,
            password: 'wrong-password',
        });
        expect(response.status()).toBe(302);
        expect(response.headers().location ?? '').toMatch(/\/login/);
    });

    test('guest is redirected to login from dashboard', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login/);
    });
});
