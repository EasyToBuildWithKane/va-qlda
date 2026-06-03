import { applyGeneratedCommitMessage } from './apply-generated-commit-msg.mjs';

const commitMsgFile = process.argv[2];

if (!commitMsgFile) {
    process.exit(0);
}

const result = applyGeneratedCommitMessage(commitMsgFile, {
    requireStaged: true,
    logLabel: 'Đã thay message IDE',
});

if (result === 'fail') {
    process.stderr.write(
        'commitlint: message không hợp lệ và không có file staged.\n'
        + 'Dùng: git add … rồi commit — hoặc npm run commit\n',
    );
    process.exit(1);
}
