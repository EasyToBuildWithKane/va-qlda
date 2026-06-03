import fs from 'node:fs';
import { generateCommitMessage } from './generate-commit-msg.mjs';
import { isValidConventional, shouldReplaceWithGenerated } from './commit-message-utils.mjs';

const commitMsgFile = process.argv[2];

if (!commitMsgFile) {
    process.exit(0);
}

const current = fs.readFileSync(commitMsgFile, 'utf8');
const userLine = current
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find((line) => line && !line.startsWith('#'));

if (userLine && isValidConventional(userLine)) {
    process.exit(0);
}

if (!shouldReplaceWithGenerated(userLine)) {
    process.exit(0);
}

const generated = generateCommitMessage();
if (!generated) {
    process.stderr.write(
        'commitlint: message không hợp lệ và không có file staged.\n'
        + 'Dùng format: feat(scope): mô tả — hoặc chạy: npm run commit:msg\n',
    );
    process.exit(1);
}

const commentBlock = current
    .split(/\r?\n/)
    .filter((line) => line.startsWith('#'))
    .join('\n');

const body = commentBlock ? `${generated}\n\n${commentBlock}\n` : `${generated}\n`;
fs.writeFileSync(commitMsgFile, body, 'utf8');
process.stderr.write(`▶ Đã thay message IDE bằng: ${generated}\n`);
