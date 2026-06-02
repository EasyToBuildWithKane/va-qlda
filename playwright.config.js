import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, devices } from '@playwright/test';
import { e2eDatabaseEnv } from './tests/e2e/helpers/database.js';

const rootDir = path.dirname(fileURLToPath(import.meta.url));
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const isCI = !!process.env.CI;

export default defineConfig({
    globalSetup: './tests/e2e/global-setup.js',
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: isCI,
    retries: isCI ? 2 : 0,
    workers: isCI ? 1 : undefined,
    reporter: isCI
        ? [['github'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'on-failure' }]],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            testIgnore: '**/visual/**',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'visual',
            testDir: './tests/e2e/visual',
            snapshotPathTemplate: '{testDir}/snapshots/{testFilePath}/{arg}{ext}',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        cwd: rootDir,
        url: baseURL,
        reuseExistingServer: !isCI,
        timeout: 120_000,
        env: {
            ...process.env,
            APP_ENV: process.env.APP_ENV ?? 'local',
            APP_DEBUG: process.env.APP_DEBUG ?? 'true',
            APP_URL: baseURL,
            ...e2eDatabaseEnv,
        },
    },
});
