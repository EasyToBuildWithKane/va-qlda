import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
    test('login page renders', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'Team & Project Governance' })).toBeVisible();
        await expect(page.getByLabel('Username')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    });

    test('member can sign in and reach dashboard', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username').fill('member');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.getByRole('heading', { name: 'Bảng điều khiển' })).toBeVisible();
        await expect(page.getByText('Foundation ready')).toBeVisible();
    });

    test('rejects invalid credentials', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username').fill('not-a-real-user');
        await page.getByLabel('Password').fill('wrong-password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
    });

    test('guest is redirected to login from dashboard', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login/);
    });
});
