import { execSync } from 'node:child_process';
import { generateCommitMessage } from './generate-commit-msg.mjs';

const message = generateCommitMessage();

if (!message) {
    console.error('❌ Không có file staged. Chạy: git add ...');
    process.exit(1);
}

console.log(`▶ Commit message: ${message}`);

try {
    execSync(`git commit -m "${message.replace(/"/g, '\\"')}"`, { stdio: 'inherit' });
} catch {
    process.exit(1);
}
