import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, devices } from '@playwright/test';
import { e2eDatabaseEnv } from './tests/e2e/helpers/database.js';

const rootDir = path.dirname(fileURLToPath(import.meta.url));
const e2ePort = process.env.PLAYWRIGHT_E2E_PORT ?? '8000';
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${e2ePort}`;
const isCI = !!process.env.CI;

export default defineConfig({
    globalSetup: './tests/e2e/global-setup.js',
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: isCI,
    retries: isCI ? 2 : 0,
    // Single worker: shared SQLite E2E DB is not safe under parallel workers.
    workers: 1,
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
            testIgnore: ['**/visual/**', '**/smoke/**'],
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'visual',
            testDir: './tests/e2e/visual',
            snapshotPathTemplate: '{testDir}/snapshots/{testFilePath}/{arg}{ext}',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'smoke',
            testDir: './tests/e2e/smoke',
            use: {
                ...devices['Desktop Chrome'],
                screenshot: 'on',
                video: 'off',
            },
        },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${e2ePort}`,
        cwd: rootDir,
        url: baseURL,
        // Always spawn with e2eDatabaseEnv unless explicitly opted in (avoids stale MySQL serve on :8000).
        reuseExistingServer: process.env.PLAYWRIGHT_REUSE_SERVER === '1',
        timeout: 120_000,
        env: {
            ...process.env,
            APP_ENV: process.env.APP_ENV ?? 'local',
            APP_DEBUG: process.env.APP_DEBUG ?? 'true',
            APP_URL: baseURL,
            AUTH_PASSWORD_LOGIN: 'true',
            SESSION_DRIVER: 'file',
            SESSION_SECURE_COOKIE: 'false',
            ...e2eDatabaseEnv,
        },
    },
});
