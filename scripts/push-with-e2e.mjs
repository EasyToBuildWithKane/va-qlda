import { spawnSync } from 'node:child_process';

const args = process.argv.slice(2);
const env = { ...process.env, RUN_E2E_ON_PUSH: '1' };

const result = spawnSync('git', ['push', ...args], {
    stdio: 'inherit',
    env,
    shell: process.platform === 'win32',
});

process.exit(result.status ?? 1);
