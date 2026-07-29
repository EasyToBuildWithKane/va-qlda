import { execSync } from 'node:child_process';
import { generateCommitMessageWithBody } from './generate-commit-msg.mjs';

const C = {
    reset: '\x1b[0m',
    dim: '\x1b[2m',
    bold: '\x1b[1m',
    brand: '\x1b[38;5;161m', // ~ #9A0036
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    cyan: '\x1b[36m',
};

const supportsColor = process.stdout.isTTY && !process.env.NO_COLOR;
const paint = (color, text) => (supportsColor ? `${color}${text}${C.reset}` : text);

function box(title) {
    const width = 56;
    const line = '─'.repeat(width);
    console.log(paint(C.brand, `┌${line}┐`));
    console.log(paint(C.brand, '│ ') + paint(C.bold, title.padEnd(width - 2)) + paint(C.brand, ' │'));
    console.log(paint(C.brand, `└${line}┘`));
}

/** @returns {{ count: number, lines: string[], added: number, deleted: number }} */
function stagedSummary() {
    try {
        const out = execSync('git diff --cached --name-status', { encoding: 'utf8' }).trim();
        const lines = out ? out.split(/\r?\n/).filter(Boolean) : [];

        let added = 0;
        let deleted = 0;
        const numstat = execSync('git diff --cached --numstat', { encoding: 'utf8' }).trim();
        for (const l of numstat.split(/\r?\n/).filter(Boolean)) {
            const [a, d] = l.split('\t');
            added += a === '-' ? 0 : Number.parseInt(a, 10) || 0;
            deleted += d === '-' ? 0 : Number.parseInt(d, 10) || 0;
        }
        return { count: lines.length, lines, added, deleted };
    } catch {
        return { count: 0, lines: [], added: 0, deleted: 0 };
    }
}

const STATUS_ICON = { A: '＋', M: '∙', D: '－', R: '→', C: '⎘' };
function statusLabel(status) {
    const key = status[0];
    const icon = STATUS_ICON[key] ?? '∙';
    const color = key === 'A' ? C.green : key === 'D' ? C.red : key === 'R' ? C.cyan : C.dim;
    return paint(color, icon);
}

const { count, lines, added, deleted } = stagedSummary();
const generated = generateCommitMessageWithBody();

if (!generated) {
    console.error('');
    console.error(paint(C.red, '✗ Không tạo được message commit.'));
    console.error(paint(C.dim, '  • Chưa có file staged → chạy: git add <file> hoặc git add -A'));
    console.error(paint(C.dim, '  • Hoặc tự gõ: git commit -m "feat(scope): mô tả"'));
    console.error('');
    process.exit(1);
}

console.log('');
box('VA-Workspace · Auto Commit');
console.log('');
console.log(
    paint(C.bold, '  📦 Staged  ') +
        paint(C.cyan, `${count} file(s)`) +
        paint(C.dim, '   ') +
        paint(C.green, `+${added}`) +
        paint(C.dim, ' / ') +
        paint(C.red, `-${deleted}`),
);

const MAX_LIST = 12;
const toShow = lines.slice(0, MAX_LIST);
for (const line of toShow) {
    const tab = line.indexOf('\t');
    const status = line.slice(0, tab).trim();
    const path = line.slice(tab + 1).trim();
    console.log(`     ${statusLabel(status)} ${paint(C.dim, path)}`);
}
if (lines.length > MAX_LIST) {
    console.log(paint(C.dim, `     … và ${lines.length - MAX_LIST} file khác`));
}

console.log('');
console.log(paint(C.bold, '  📝 Message'));
console.log(`     ${paint(C.brand, generated.header)}`);
if (generated.body) {
    for (const line of generated.body.split('\n')) {
        console.log(`     ${paint(C.dim, line)}`);
    }
}

console.log('');
console.log(paint(C.yellow, '  ▶ Đang chạy pre-commit (lint-staged)…'));
console.log('');

try {
    const fullMessage = generated.body ? `${generated.header}\n\n${generated.body}` : generated.header;
    execSync(`git commit -m "${fullMessage.replace(/"/g, '\\"')}"`, { stdio: 'inherit' });
    console.log('');
    console.log(paint(C.green, '  ✓ Commit thành công.'));
    console.log('');
} catch {
    console.error('');
    console.error(paint(C.red, '  ✗ Commit thất bại (thường do pre-commit: Pint / ESLint).'));
    console.error(paint(C.dim, '    Sửa lỗi rồi chạy lại: npm run commit'));
    console.error('');
    process.exit(1);
}
