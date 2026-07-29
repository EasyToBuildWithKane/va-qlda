import { test, expect } from '@playwright/test';
import { postLogin } from '../helpers/loginPost.js';
import { captureFeatureScreen } from '../helpers/visualCapture.js';

/**
 * Mỗi màn hình tính năng (live) → một snapshot baseline.
 * So sánh khi chạy: npm run test:e2e:visual
 * Cập nhật sau khi đổi UI: npm run test:e2e:visual -- --update-snapshots
 */
async function login(page, username) {
    const response = await postLogin(page, { username, password: 'password' });
    expect(response.status(), 'E2E login — bật AUTH_PASSWORD_LOGIN').toBeLessThan(400);
}

/** @type {Array<{ id: string, path: string, heading: RegExp | string }>} */
const ADMIN_SCREENS = [
    { id: 'dashboard', path: '/dashboard', heading: 'Bảng điều khiển' },
    { id: 'projects-index', path: '/projects', heading: /dự án/i },
    { id: 'projects-create', path: '/projects/create', heading: 'Tạo dự án mới' },
    { id: 'daily-reports-today', path: '/daily-reports/today', heading: /báo cáo hôm nay/i },
    { id: 'daily-reports-history', path: '/daily-reports', heading: /lịch sử báo cáo/i },
    { id: 'daily-reports-review', path: '/daily-reports/review', heading: /chờ phê duyệt/i },
    { id: 'blockers', path: '/blockers', heading: /vướng mắc/i },
    { id: 'departments', path: '/departments', heading: 'Quản lý phòng ban' },
    { id: 'feedback', path: '/feedback', heading: /phản hồi/i },
    { id: 'knowledge-base', path: '/knowledge-base', heading: /cơ sở tri thức/i },
    { id: 'ai-accounts', path: '/ai-accounts', heading: /tài khoản ai/i },
    { id: 'ai-accounts-dashboard', path: '/ai-accounts/dashboard', heading: /dashboard quản trị ai/i },
    { id: 'ai-accounts-analytics', path: '/ai-accounts/analytics', heading: /báo cáo phân tích/i },
    { id: 'ai-accounts-cost-report', path: '/ai-accounts/cost-report', heading: /pđx & đntt/i },
    { id: 'notifications-manage', path: '/notifications/manage', heading: /quản lý thông báo/i },
];

test.describe('Visual — snapshot theo màn hình', () => {
    test('login', async ({ page }) => {
        await page.goto('/tech/login');
        await expect(page.getByRole('heading', { name: 'Đăng nhập', level: 1 })).toBeVisible();
        await captureFeatureScreen(page, 'login');
    });

    test.describe('member', () => {
        test.beforeEach(async ({ page }) => {
            await login(page, 'member');
        });

        test('dashboard', async ({ page }) => {
            await captureFeatureScreen(page, 'dashboard-member', {
                path: '/dashboard',
                heading: 'Bảng điều khiển',
            });
        });

        test('daily-reports-today', async ({ page }) => {
            await captureFeatureScreen(page, 'member-daily-reports-today', {
                path: '/daily-reports/today',
                heading: /báo cáo hôm nay/i,
            });
        });
    });

    test.describe('admin', () => {
        test.beforeEach(async ({ page }) => {
            await login(page, 'admin');
        });

        for (const screen of ADMIN_SCREENS) {
            test(screen.id, async ({ page }) => {
                await captureFeatureScreen(page, `admin-${screen.id}`, {
                    path: screen.path,
                    heading: screen.heading,
                });
            });
        }

        test('project-show-qlda', async ({ page }) => {
            await page.goto('/projects');
            await page.waitForLoadState('networkidle');
            await page.getByRole('link', { name: /QLDA|Quản lý dự án/i }).first().click();
            await page.waitForURL(/\/projects\/\d+/);
            await page.waitForLoadState('networkidle');
            await captureFeatureScreen(page, 'admin-project-show-qlda');
        });
    });
});
