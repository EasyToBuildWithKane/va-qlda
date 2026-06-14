import { test, expect } from './helpers/auth.js';

test.describe('Knowledge Base', () => {
    test('member can open knowledge base index', async ({ page }) => {
        await page.goto('/knowledge-base');
        await expect(page).toHaveURL(/\/knowledge-base/);
        await expect(page.getByRole('heading', { name: /cơ sở tri thức/i })).toBeVisible();
    });

    test('member can open create article page', async ({ page }) => {
        await page.goto('/knowledge-base/articles/create');
        await expect(page.getByRole('heading', { name: /viết bài/i })).toBeVisible();
    });
});

test.describe('Coaching', () => {
    test.use({ role: 'admin' });

    test('admin can open coaching dashboard', async ({ page }) => {
        await page.goto('/coaching');
        await expect(page).toHaveURL(/\/coaching/);
        await expect(page.getByRole('heading', { name: /coaching/i })).toBeVisible();
    });
});
