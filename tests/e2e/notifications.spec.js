import { test, expect } from './helpers/auth.js';

test.describe('Notifications (JSON API)', () => {
    test('member sees notification bell on dashboard', async ({ page }) => {
        await page.goto('/dashboard');

        const bell = page.getByRole('button', { name: 'Thông báo' });
        await expect(bell.first()).toBeVisible();
    });

    test('unread count endpoint returns json', async ({ page, request }) => {
        await page.goto('/dashboard');

        const cookies = await page.context().cookies();
        const cookieHeader = cookies.map((c) => `${c.name}=${c.value}`).join('; ');

        const res = await request.get('/notifications/unread-count', {
            headers: { Cookie: cookieHeader, Accept: 'application/json' },
        });

        expect(res.ok()).toBeTruthy();
        const body = await res.json();
        expect(body).toHaveProperty('count');
    });
});
