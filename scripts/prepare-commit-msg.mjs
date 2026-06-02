import fs from 'node:fs';
import { generateCommitMessage } from './generate-commit-msg.mjs';

const [commitMsgFile, source = ''] = process.argv.slice(2);

if (!commitMsgFile) {
    process.exit(0);
}

if (['merge', 'squash', 'commit'].includes(source)) {
    process.exit(0);
}

const current = fs.readFileSync(commitMsgFile, 'utf8');
const userLine = current
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find((line) => line && !line.startsWith('#'));

if (userLine && (source === 'message' || source === 'template')) {
    process.exit(0);
}

const generated = generateCommitMessage();
if (!generated) {
    process.exit(0);
}

fs.writeFileSync(commitMsgFile, `${generated}\n`, 'utf8');
