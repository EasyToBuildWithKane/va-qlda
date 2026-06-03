/** Conventional Commits header (commitlint-compatible). */
const CONVENTIONAL =
    /^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([\w./-]+\))?!?: [a-z0-9].+$/;

const IDE_PLACEHOLDERS = /^(updates?|wip|changes?|commit|fixed?|misc)$/i;

export function isValidConventional(line) {
    if (!line || line.length < 10) {
        return false;
    }
    return CONVENTIONAL.test(line.trim());
}

/** Messages from IDE Sync / generic templates that should be auto-replaced. */
export function shouldReplaceWithGenerated(line) {
    const t = (line ?? '').trim();
    if (!t) {
        return true;
    }
    if (IDE_PLACEHOLDERS.test(t)) {
        return true;
    }
    return !isValidConventional(t);
}
