import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { e2eDatabaseEnv, e2eDatabasePath } from './database.js';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');

/**
 * Ensure SQLite E2E database exists and is seeded.
 */
export function ensureE2EDatabase() {
    fs.mkdirSync(path.dirname(e2eDatabasePath), { recursive: true });

    if (!fs.existsSync(e2eDatabasePath)) {
        fs.closeSync(fs.openSync(e2eDatabasePath, 'w'));
    }

    const env = { ...process.env, ...e2eDatabaseEnv };

    execSync('php artisan migrate:fresh --force --seed', {
        stdio: 'inherit',
        env,
        cwd: projectRoot,
        shell: process.platform === 'win32',
    });
}
