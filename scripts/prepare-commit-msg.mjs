import { applyGeneratedCommitMessage } from './apply-generated-commit-msg.mjs';

const [commitMsgFile, source = ''] = process.argv.slice(2);

if (!commitMsgFile) {
    process.exit(0);
}

if (['merge', 'squash', 'commit'].includes(source)) {
    process.exit(0);
}

const result = applyGeneratedCommitMessage(commitMsgFile, {
    requireStaged: false,
    logLabel: 'Message commit (prepare)',
});

if (result === 'fail') {
    process.exit(1);
}
