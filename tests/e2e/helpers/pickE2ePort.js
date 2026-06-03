/**
 * Print first free TCP port in [8001..8010] for pre-push / local E2E (stdout only).
 */
import net from 'node:net';

const from = Number(process.env.PLAYWRIGHT_E2E_PORT_FROM ?? 8001);
const to = Number(process.env.PLAYWRIGHT_E2E_PORT_TO ?? 8020);

function portFree(port) {
    return new Promise((resolve) => {
        const server = net.createServer();
        server.once('error', () => resolve(false));
        server.once('listening', () => {
            server.close(() => resolve(true));
        });
        server.listen(port, '127.0.0.1');
    });
}

for (let port = from; port <= to; port += 1) {
    // eslint-disable-next-line no-await-in-loop
    if (await portFree(port)) {
        process.stdout.write(String(port));
        process.exit(0);
    }
}

console.error(`No free port between ${from} and ${to} for E2E. Stop stale "php artisan serve" or Playwright.`);
process.exit(1);
