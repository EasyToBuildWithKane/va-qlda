import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '../helpers/auth.js';

const rootDir = path.dirname(fileURLToPath(import.meta.url));
const screenshotDir = path.resolve(rootDir, '../../../test-results/screenshots');

test('smoke: dashboard member — full page screenshot', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: 'Bảng điều khiển' })).toBeVisible();

    fs.mkdirSync(screenshotDir, { recursive: true });
    const manualPath = path.join(screenshotDir, 'dashboard-member-full.png');
    await page.screenshot({ path: manualPath, fullPage: true });

    // eslint-disable-next-line no-console -- local smoke output
    console.log(`\n📸 Screenshot: ${manualPath}\n`);
});
