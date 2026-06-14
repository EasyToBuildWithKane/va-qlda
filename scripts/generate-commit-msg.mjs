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
        path.startsWith('_dev/') ||
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

/**
 * Gợi ý scope + subject + body theo module VA-QLDA (ưu tiên hơn mô tả generic).
 * @param {string[]} paths
 * @returns {{ type?: string, scope?: string, subject: string, body?: string[] }|null}
 */
function detectFeatureTheme(paths) {
    const text = paths.join(' ');
    const body = [];

    const hasBlocker =
        /Blocker|blockers/i.test(text) && (text.includes('app/') || text.includes('resources/js/'));
    const hasTelegram = /Telegram|telegram\.php/.test(text);
    const hasRealtime =
        /useCommentRealtime|realtime\/|_dev\/realtime|CommentThread|CommentRealtime/.test(text);
    const hasDailyReport = /DailyReport|daily-report/.test(text);
    const hasKnowledgeBase = /KnowledgeBase|knowledge-base|KbArticle/.test(text);
    const hasCoaching = /Coaching|coaching/.test(text);

    if (hasKnowledgeBase && hasCoaching) {
        return {
            type: 'feat',
            scope: 'kb-coaching',
            subject: 'knowledge base and coaching modules v1',
            body: [
                'KB: articles, FULLTEXT search, gallery, export, datagrid toolbar.',
                'Coaching: courses, sessions, dashboard, Excel export, safe embeds.',
                'PHPStan level 5 fixes for CI.',
            ],
        };
    }

    if (hasKnowledgeBase) {
        return {
            type: 'feat',
            scope: 'knowledge-base',
            subject: 'internal knowledge base v1',
            body: ['Articles, search, gallery images, CSV/Excel export.'],
        };
    }

    if (hasCoaching) {
        return {
            type: 'feat',
            scope: 'coaching',
            subject: 'coaching and mentoring module v1',
            body: ['Courses, sessions, financial dashboard, styled export.'],
        };
    }

    if (hasBlocker && hasTelegram) {
        body.push('Notify Telegram on each status change; lock resolved/closed from reverting.');
    } else if (hasBlocker && /UpdateBlockerRequest|isTerminal|statusLocked/.test(text)) {
        body.push('Lock resolved/closed status on API and UI.');
    }

    if (hasBlocker && /CommentThread|partialReloadKeys|blockersList/.test(text)) {
        body.push('Reload blockers after comment post; sync detail modal by id.');
    }

    if (hasRealtime) {
        body.push('Socket.IO reconnect re-subscribes room; Realtime badge in CommentThread.');
        if (text.includes('_dev/')) {
            body.push('Deploy notes: Redis, Node :6001, OLS /socket.io/ proxy.');
        }
    }

    if (hasDailyReport && hasTelegram) {
        return {
            type: 'feat',
            scope: 'daily-report',
            subject: 'vietnamese telegram for review and reject',
            body: body.length ? body : undefined,
        };
    }

    if (hasBlocker && hasRealtime && hasTelegram) {
        return {
            type: 'feat',
            scope: 'blockers',
            subject: 'status lock, telegram, comments and realtime',
            body: body.length ? body : undefined,
        };
    }

    if (hasBlocker && hasRealtime) {
        return {
            type: 'feat',
            scope: 'blockers',
            subject: 'comment reload and realtime reconnect',
            body: body.length ? body : undefined,
        };
    }

    if (hasBlocker && hasTelegram) {
        return {
            type: 'feat',
            scope: 'blockers',
            subject: 'telegram status notify and lock terminal state',
            body: body.length ? body : undefined,
        };
    }

    if (hasBlocker && body.length) {
        return {
            type: 'fix',
            scope: 'blockers',
            subject: 'trao doi tab and blocker workflow',
            body,
        };
    }

    if (hasRealtime && !hasBlocker) {
        return {
            type: 'feat',
            scope: 'realtime',
            subject: 'improve comment socket.io subscription',
            body: body.length ? body : undefined,
        };
    }

    if (paths.every(isDocs) && hasRealtime) {
        return {
            type: 'docs',
            scope: 'realtime',
            subject: 'socket.io deploy and openlitespeed proxy',
            body: body.length ? body : undefined,
        };
    }

    return null;
}

function buildSubject(type, changes) {
    const paths = changes.map((c) => c.path);
    const theme = detectFeatureTheme(paths);
    if (theme?.subject) {
        return theme.subject;
    }

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

    const themeScope = detectFeatureTheme(paths)?.scope;
    if (themeScope) {
        return themeScope;
    }

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

    if (paths.some((p) => p.includes('phpstan') || p === 'phpstan.neon.dist')) {
        return 'ci';
    }

    const themeType = detectFeatureTheme(paths)?.type;
    if (themeType) {
        return themeType;
    }

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
 * @returns {{ header: string, body: string }|null}
 */
export function generateCommitMessageWithBody() {
    const changes = getStagedChanges();
    const paths = changes.map((c) => c.path);
    const type = detectType(changes);

    if (!type) {
        return null;
    }

    const theme = detectFeatureTheme(paths);
    const scope = theme?.scope ?? detectScope(changes);
    const subject = buildSubject(type, changes);
    const headerRaw = scope ? `${type}(${scope}): ${subject}` : `${type}: ${subject}`;
    const header = headerRaw.length > 72 ? `${headerRaw.slice(0, 69)}...` : headerRaw;

    const bodyLines = (theme?.body ?? [])
        .map((line) => line.trim())
        .filter(Boolean)
        .filter((line) => line.length <= 100);

    const body = bodyLines.join('\n');

    return { header, body };
}

/**
 * @returns {string|null}
 */
export function generateCommitMessage() {
    const result = generateCommitMessageWithBody();
    if (!result) {
        return null;
    }

    if (result.body) {
        return `${result.header}\n\n${result.body}`;
    }

    return result.header;
}

if (process.argv[1]?.endsWith('generate-commit-msg.mjs')) {
    const message = generateCommitMessage();
    if (message) {
        process.stdout.write(message);
    }
    process.exit(message ? 0 : 1);
}
