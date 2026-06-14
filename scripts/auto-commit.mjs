import { execSync } from 'node:child_process';
import { generateCommitMessageWithBody } from './generate-commit-msg.mjs';

function stagedSummary() {
    try {
        const out = execSync('git diff --cached --name-status', { encoding: 'utf8' }).trim();
        if (!out) {
            return { count: 0, lines: [] };
        }
        const lines = out.split(/\r?\n/).filter(Boolean);
        return { count: lines.length, lines };
    } catch {
        return { count: 0, lines: [] };
    }
}

const { count, lines } = stagedSummary();
const generated = generateCommitMessageWithBody();

if (!generated) {
    console.error('');
    console.error('❌ Không tạo được message commit.');
    console.error('   • Chưa có file staged → chạy: git add <file> hoặc git add -A');
    console.error('   • Hoặc gõ message thủ công: git commit -m "feat(scope): mô tả"');
    console.error('');
    process.exit(1);
}

console.log('');
console.log('📦 Staged:', count, 'file(s)');
if (lines.length > 0 && lines.length <= 12) {
    for (const line of lines) {
        console.log('   ', line);
    }
} else if (lines.length > 12) {
    for (const line of lines.slice(0, 8)) {
        console.log('   ', line);
    }
    console.log(`   … và ${lines.length - 8} file khác`);
}
console.log('');
console.log('📝 Commit message:');
console.log('   ', generated.header);
if (generated.body) {
    for (const line of generated.body.split('\n')) {
        console.log('   ', line);
    }
}
console.log('');
console.log('▶ Đang chạy pre-commit hook (lint-staged)…');
console.log('');

try {
    const fullMessage = generated.body
        ? `${generated.header}\n\n${generated.body}`
        : generated.header;
    execSync(`git commit -m "${fullMessage.replace(/"/g, '\\"')}"`, { stdio: 'inherit' });
    console.log('');
    console.log('✅ Commit thành công.');
} catch {
    console.error('');
    console.error('❌ Commit thất bại (thường do pre-commit: Pint / ESLint).');
    console.error('   Sửa lỗi rồi chạy lại: npm run commit');
    console.error('');
    process.exit(1);
}
