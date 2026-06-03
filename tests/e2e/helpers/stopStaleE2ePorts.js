/**
 * Stop processes LISTENING on 127.0.0.1:8001–8010 (stale php artisan serve from E2E).
 * Does not touch :8000 (typical dev server).
 */
import { execSync } from 'node:child_process';
import os from 'node:os';

const from = Number(process.env.PLAYWRIGHT_E2E_PORT_FROM ?? 8001);
const to = Number(process.env.PLAYWRIGHT_E2E_PORT_TO ?? 8010);

function portInRange(port) {
    return port >= from && port <= to;
}

function killWindows() {
    let out;
    try {
        out = execSync('netstat -ano', { encoding: 'utf8', windowsHide: true });
    } catch {
        return;
    }

    const pids = new Set();
    for (const line of out.split(/\r?\n/)) {
        if (!line.includes('LISTENING')) {
            continue;
        }
        const match = line.match(/127\.0\.0\.1:(\d+)\s+\S+\s+LISTENING\s+(\d+)/);
        if (!match) {
            continue;
        }
        const port = Number(match[1]);
        if (!portInRange(port)) {
            continue;
        }
        pids.add(match[2]);
    }

    for (const pid of pids) {
        if (pid === '0') {
            continue;
        }
        try {
            execSync(`taskkill /F /PID ${pid}`, { stdio: 'ignore', windowsHide: true });
            process.stderr.write(`Stopped stale E2E listener PID ${pid}\n`);
        } catch {
            // already gone
        }
    }
}

function killUnix() {
    for (let port = from; port <= to; port += 1) {
        try {
            const pid = execSync(`lsof -ti tcp:${port} -sTCP:LISTEN`, {
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'ignore'],
            }).trim();
            if (pid) {
                execSync(`kill -9 ${pid}`, { stdio: 'ignore' });
                process.stderr.write(`Stopped stale E2E listener on :${port} PID ${pid}\n`);
            }
        } catch {
            // none
        }
    }
}

if (process.env.PLAYWRIGHT_SKIP_STOP_STALE === '1') {
    process.exit(0);
}

if (os.platform() === 'win32') {
    killWindows();
} else {
    killUnix();
}
