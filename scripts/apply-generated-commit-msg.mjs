import fs from 'node:fs';
import { generateCommitMessage } from './generate-commit-msg.mjs';
import { isValidConventional, shouldReplaceWithGenerated } from './commit-message-utils.mjs';

/**
 * @param {string} commitMsgFile
 * @param {{ requireStaged?: boolean, logLabel?: string }} options
 * @returns {'ok'|'skip'|'fail'}
 */
export function applyGeneratedCommitMessage(commitMsgFile, options = {}) {
    const { requireStaged = false, logLabel = 'Đã gợi ý commit' } = options;

    const current = fs.readFileSync(commitMsgFile, 'utf8');
    const userLine = current
        .split(/\r?\n/)
        .map((line) => line.trim())
        .find((line) => line && !line.startsWith('#'));

    if (userLine && isValidConventional(userLine)) {
        return 'skip';
    }

    if (userLine && !shouldReplaceWithGenerated(userLine)) {
        return 'skip';
    }

    const generated = generateCommitMessage();
    if (!generated) {
        return requireStaged ? 'fail' : 'skip';
    }

    const commentBlock = current
        .split(/\r?\n/)
        .filter((line) => line.startsWith('#'))
        .join('\n');

    const body = commentBlock ? `${generated}\n\n${commentBlock}\n` : `${generated}\n`;
    fs.writeFileSync(commitMsgFile, body, 'utf8');
    const preview = generated.split('\n')[0];
    process.stderr.write(`▶ ${logLabel}: ${preview}\n`);

    return 'ok';
}
