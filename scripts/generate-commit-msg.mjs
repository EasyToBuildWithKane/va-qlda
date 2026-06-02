import { execSync } from 'node:child_process';

/**
 * @returns {{ status: string, path: string }[]}
 */
function getStagedChanges() {
    try {
        const output = execSync('git diff --cached --name-status', { encoding: 'utf8' }).trim();
        if (!output) {
            return [];
        }

        return output.split(/\r?\n/).map((line) => {
            const tab = line.indexOf('\t');
            const status = line.slice(0, tab).trim();
            const path = line.slice(tab + 1).trim();

            return { status, path };
        });
    } catch {
        return [];
    }
}

function isDocs(path) {
    return (
        path.startsWith('docs/') ||
        path.startsWith('.cursor/') ||
        path.startsWith('.claude/') ||
        path === 'README.md' ||
        path === 'CLAUDE.md'
    );
}

function isCi(path) {
    return (
        path.startsWith('.github/') ||
        path.startsWith('.husky/') ||
        path === '.gitlab-ci.yml' ||
        path.includes('playwright.config') ||
        path.includes('eslint.config') ||
        path === 'commitlint.config.js'
    );
}

function isTest(path) {
    return path.startsWith('tests/');
}

function isFrontend(path) {
    return path.startsWith('resources/js/');
}

function isBackend(path) {
    return (
        path.startsWith('app/') ||
        path.startsWith('database/') ||
        path.startsWith('routes/') ||
        path.startsWith('config/') ||
        path.startsWith('bootstrap/')
    );
}

function isDeps(path) {
    return (
        path === 'package.json' ||
        path === 'package-lock.json' ||
        path === 'composer.json' ||
        path === 'composer.lock'
    );
}

function isStyleOnly(paths) {
    return paths.every(
        (p) =>
            p.endsWith('.css') ||
            p.endsWith('.vue') ||
            p.endsWith('.js') ||
            p.endsWith('.php') ||
            p === '.gitignore',
    );
}

function describeArea(path) {
    const rules = [
        [/^resources\/js\/Pages\//, 'pages'],
        [/^resources\/js\/Components\//, 'components'],
        [/^resources\/js\/composables\//, 'composables'],
        [/^resources\/js\/constants\//, 'constants'],
        [/^app\/Http\/Controllers\//, 'controllers'],
        [/^app\/Models\//, 'models'],
        [/^app\/Application\//, 'use cases'],
        [/^database\/migrations\//, 'migrations'],
        [/^database\/factories\//, 'factories'],
        [/^database\/seeders\//, 'seeders'],
        [/^tests\/Feature\//, 'feature tests'],
        [/^tests\/e2e\//, 'e2e tests'],
        [/^tests\/Unit\//, 'unit tests'],
        [/^\.cursor\//, 'cursor config'],
        [/^\.husky\//, 'git hooks'],
        [/^\.github\//, 'github actions'],
        [/^docs\//, 'documentation'],
        [/^config\//, 'config'],
    ];

    for (const [pattern, label] of rules) {
        if (pattern.test(path)) {
            return label;
        }
    }

    const top = path.split('/')[0];
    return top.replace(/^\./, '') || 'project';
}

function buildSubject(type, changes) {
    const paths = changes.map((c) => c.path);
    const areas = [...new Set(paths.map(describeArea))].slice(0, 3);
    const areaText = areas.join(', ');

    const added = changes.filter((c) => c.status.startsWith('A')).length;
    const deleted = changes.filter((c) => c.status.startsWith('D')).length;
    const modified = changes.filter((c) => c.status.startsWith('M')).length;

    let verb = 'update';
    if (added > 0 && modified === 0 && deleted === 0) {
        verb = 'add';
    } else if (deleted > 0 && added === 0 && modified === 0) {
        verb = 'remove';
    } else if (type === 'fix') {
        verb = 'fix';
    } else if (type === 'refactor') {
        verb = 'refactor';
    }

    const templates = {
        docs: `update ${areaText || 'documentation'}`,
        ci: `update ${areaText || 'ci pipeline'}`,
        test: `${verb} ${areaText || 'tests'}`,
        chore: `update ${areaText || 'project tooling'}`,
        build: `update ${areaText || 'build config'}`,
        style: `format ${areaText || 'code style'}`,
        fix: `fix ${areaText || 'application logic'}`,
        feat: `${verb} ${areaText || 'application features'}`,
        refactor: `refactor ${areaText || 'code'}`,
        perf: `improve ${areaText || 'performance'}`,
        revert: `revert ${areaText || 'changes'}`,
    };

    return templates[type] ?? `update ${areaText || 'project files'}`;
}

function detectScope(changes) {
    const paths = changes.map((c) => c.path);

    if (paths.every(isFrontend)) {
        const match = paths[0]?.match(/^resources\/js\/Pages\/([^/]+)/);
        if (match) {
            return match[1].toLowerCase();
        }
        return 'frontend';
    }

    if (paths.every((p) => p.startsWith('app/Application/DailyReport/'))) {
        return 'daily-report';
    }

    if (paths.every((p) => p.startsWith('app/') && p.includes('Project'))) {
        return 'project';
    }

    if (paths.every(isTest)) {
        return 'test';
    }

    return null;
}

/**
 * @param {{ status: string, path: string }[]} changes
 */
function detectType(changes) {
    const paths = changes.map((c) => c.path);

    if (paths.length === 0) {
        return null;
    }

    if (paths.every(isDocs)) {
        return 'docs';
    }

    if (paths.every(isCi)) {
        return 'ci';
    }

    if (paths.every(isDeps)) {
        return 'chore';
    }

    if (paths.every(isTest)) {
        return 'test';
    }

    const hasCi = paths.some(isCi);
    const hasDocs = paths.some(isDocs);
    const hasTest = paths.some(isTest);
    const hasBackend = paths.some(isBackend);
    const hasFrontend = paths.some(isFrontend);

    if (hasCi && !hasBackend && !hasFrontend) {
        return 'ci';
    }

    if (hasDocs && !hasBackend && !hasFrontend) {
        return 'docs';
    }

    if (hasTest && !hasBackend && !hasFrontend) {
        return 'test';
    }

    const newMigration = changes.some((c) => c.status.startsWith('A') && c.path.includes('database/migrations/'));
    if (newMigration) {
        return 'feat';
    }

    const newBackendFiles = changes.filter((c) => c.status.startsWith('A') && isBackend(c.path));
    const newFrontendFiles = changes.filter((c) => c.status.startsWith('A') && isFrontend(c.path));

    if (newBackendFiles.length > 0 || newFrontendFiles.length > 0) {
        return 'feat';
    }

    const onlyModifications = changes.every((c) => c.status.startsWith('M') || c.status.startsWith('R'));
    if (onlyModifications && isStyleOnly(paths)) {
        return 'style';
    }

    if (onlyModifications && hasBackend && paths.some((p) => p.includes('Test.php'))) {
        return 'test';
    }

    if (onlyModifications && (hasBackend || hasFrontend)) {
        return 'fix';
    }

    if (paths.some((p) => p.includes('UseCase') || p.includes('Service'))) {
        return 'refactor';
    }

    return 'chore';
}

/**
 * @returns {string|null}
 */
export function generateCommitMessage() {
    const changes = getStagedChanges();
    const type = detectType(changes);

    if (!type) {
        return null;
    }

    const scope = detectScope(changes);
    const subject = buildSubject(type, changes);
    const header = scope ? `${type}(${scope}): ${subject}` : `${type}: ${subject}`;

    return header.length > 72 ? `${header.slice(0, 69)}...` : header;
}

if (process.argv[1]?.endsWith('generate-commit-msg.mjs')) {
    const message = generateCommitMessage();
    if (message) {
        process.stdout.write(message);
    }
    process.exit(message ? 0 : 1);
}
