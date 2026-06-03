import { expect } from '@playwright/test';

/**
 * Baseline screenshot cho regression (project `visual`).
 * Cập nhật ảnh: npm run test:e2e:visual -- --update-snapshots
 */
export async function captureFeatureScreen(page, snapshotName, options = {}) {
    const { path, heading } = options;

    if (path) {
        await page.goto(path);
    }

    await page.waitForLoadState('networkidle');

    if (heading) {
        await expect(page.getByRole('heading', { name: heading })).toBeVisible({ timeout: 15_000 });
    }

    const file = snapshotName.endsWith('.png') ? snapshotName : `${snapshotName}.png`;

    await expect(page).toHaveScreenshot(file, {
        fullPage: true,
        maxDiffPixelRatio: 0.02,
    });
}
