import { execSync } from 'node:child_process';

/**
 * Bộ sinh commit message dựa hoàn toàn trên diff đã staged (không theme hardcode).
 * Suy ra: type · scope (module thật) · subject (humanize file "đầu tàu") · body có churn stats.
 *
 * @typedef {{ status: string, path: string, added: number, deleted: number }} Change
 */

/**
 * @returns {Change[]}
 */
function getStagedChanges() {
    try {
        const nameStatus = execSync('git diff --cached --name-status', { encoding: 'utf8' }).trim();
        if (!nameStatus) {
            return [];
        }

        /** @type {Map<string, number[]>} churn[path] = [added, deleted] */
        const churn = new Map();
        const numstat = execSync('git diff --cached --numstat', { encoding: 'utf8' }).trim();
        for (const line of numstat.split(/\r?\n/).filter(Boolean)) {
            const [addedRaw, deletedRaw, ...rest] = line.split('\t');
            const path = rest.join('\t');
            const added = addedRaw === '-' ? 0 : Number.parseInt(addedRaw, 10) || 0;
            const deleted = deletedRaw === '-' ? 0 : Number.parseInt(deletedRaw, 10) || 0;
            churn.set(path, [added, deleted]);
        }

        return nameStatus.split(/\r?\n/).filter(Boolean).map((line) => {
            const tab = line.indexOf('\t');
            const status = line.slice(0, tab).trim();
            // Rename hiển thị "old\tnew" — lấy đường dẫn cuối (file đích).
            const rawPath = line.slice(tab + 1).trim();
            const path = rawPath.includes('\t') ? rawPath.split('\t').pop().trim() : rawPath;
            const [added = 0, deleted = 0] = churn.get(path) ?? churn.get(rawPath) ?? [];

            return { status, path, added, deleted };
        });
    } catch {
        return [];
    }
}

/** PascalCase / camelCase / snake_case / kebab → kebab-case thường. */
function kebab(name) {
    return name
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/[_\s]+/g, '-')
        .toLowerCase()
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

/** "CoachingSessionAssignmentsTab.vue" → "coaching session assignments tab". */
function humanize(basename) {
    return basename
        .replace(/\.[a-z0-9]+$/i, '')
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
        .trim()
        .toLowerCase();
}

// ── Phân loại đường dẫn ───────────────────────────────────────────────

const isDocs = (p) =>
    p.startsWith('docs/') || p.startsWith('_dev/') || p.startsWith('.cursor/') ||
    p.startsWith('.claude/') || p === 'README.md' || p === 'CLAUDE.md';

const isCi = (p) =>
    p.startsWith('.github/') || p.startsWith('.husky/') || p === '.gitlab-ci.yml' ||
    p.includes('playwright.config') || p.includes('eslint.config') || p === 'commitlint.config.js';

const isTest = (p) => p.startsWith('tests/');
const isFrontend = (p) => p.startsWith('resources/js/');
const isBackend = (p) =>
    p.startsWith('app/') || p.startsWith('database/') || p.startsWith('routes/') ||
    p.startsWith('config/') || p.startsWith('bootstrap/');
const isDeps = (p) =>
    p === 'package.json' || p === 'package-lock.json' || p === 'composer.json' || p === 'composer.lock';

const isStyleish = (p) =>
    p.endsWith('.css') || p.endsWith('.vue') || p.endsWith('.js') || p.endsWith('.php') || p === '.gitignore';

/** Module/domain nghiệp vụ của một đường dẫn (kebab) — null nếu không rõ. */
function domainOf(path) {
    let m;
    if ((m = path.match(/^resources\/js\/modules\/([^/]+)\//))) return kebab(m[1]);
    if ((m = path.match(/^resources\/js\/Pages\/([^/]+)\//))) return kebab(m[1]);
    if ((m = path.match(/^app\/Application\/([^/]+)\//))) return kebab(m[1]);
    if ((m = path.match(/^app\/Domain\/([^/]+)\//))) return kebab(m[1]);
    if ((m = path.match(/^app\/Http\/Controllers\/([^/]+)\//))) return kebab(m[1]);
    if ((m = path.match(/^app\/Support\/([^/]+)\//))) return kebab(m[1]);
    if (isDocs(path)) return 'docs';
    if (path.startsWith('.github/') || path.startsWith('.husky/')) return 'ci';
    if (path.startsWith('database/migrations/')) return 'database';
    if (isTest(path)) return 'tests';
    return null;
}

/** Nhãn layer/area (mịn hơn domain) cho body + subject fallback. */
function areaOf(path) {
    const rules = [
        [/^resources\/js\/modules\/[^/]+\/components\//, 'components'],
        [/^resources\/js\/modules\/[^/]+\/composables\//, 'composables'],
        [/^resources\/js\/Pages\//, 'pages'],
        [/^resources\/js\/Components\//, 'components'],
        [/^resources\/js\/composables\//, 'composables'],
        [/^resources\/js\/stores\//, 'stores'],
        [/^resources\/js\/shared\//, 'shared ui'],
        [/^app\/Http\/Controllers\//, 'controllers'],
        [/^app\/Http\/Requests\//, 'form requests'],
        [/^app\/Http\/Resources\//, 'resources'],
        [/^app\/Models\//, 'models'],
        [/^app\/Application\//, 'use cases'],
        [/^app\/Policies\//, 'policies'],
        [/^app\/Support\//, 'support'],
        [/^database\/migrations\//, 'migrations'],
        [/^database\/seeders\//, 'seeders'],
        [/^database\/factories\//, 'factories'],
        [/^tests\//, 'tests'],
        [/^\.github\//, 'github actions'],
        [/^\.husky\//, 'git hooks'],
        [/^docs\//, 'docs'],
        [/^_dev\//, 'dev docs'],
        [/^config\//, 'config'],
        [/^routes\//, 'routes'],
    ];
    for (const [re, label] of rules) {
        if (re.test(path)) return label;
    }
    return path.split('/')[0].replace(/^\./, '') || 'project';
}

// ── Type / Scope ──────────────────────────────────────────────────────

/**
 * @param {Change[]} changes
 */
function detectType(changes) {
    const paths = changes.map((c) => c.path);
    if (paths.length === 0) return null;

    if (paths.some((p) => p.includes('phpstan') || p === 'phpstan.neon.dist')) return 'ci';
    if (paths.every(isDocs)) return 'docs';
    if (paths.every(isCi)) return 'ci';
    if (paths.every(isDeps)) return 'chore';
    if (paths.every(isTest)) return 'test';

    const hasBackend = paths.some(isBackend);
    const hasFrontend = paths.some(isFrontend);

    if (paths.some(isCi) && !hasBackend && !hasFrontend) return 'ci';
    if (paths.some(isDocs) && !hasBackend && !hasFrontend) return 'docs';
    if (paths.some(isTest) && !hasBackend && !hasFrontend) return 'test';

    const newMigration = changes.some((c) => c.status.startsWith('A') && c.path.includes('database/migrations/'));
    if (newMigration) return 'feat';

    const hasNewCode = changes.some((c) => c.status.startsWith('A') && (isBackend(c.path) || isFrontend(c.path)));
    if (hasNewCode) return 'feat';

    const onlyTouched = changes.every((c) => /^[MR]/.test(c.status));
    if (onlyTouched && paths.every(isStyleish) && totalChurn(changes).deleted <= totalChurn(changes).added * 0.2) {
        // Sửa nhỏ, hầu như chỉ thêm dòng → coi như format/style.
        return 'style';
    }
    if (onlyTouched && (hasBackend || hasFrontend)) return 'fix';
    if (paths.some((p) => p.includes('UseCase') || p.includes('Service'))) return 'refactor';

    return 'chore';
}

/** Module chiếm ưu thế (theo số file) — dùng làm scope. */
function dominantDomain(changes) {
    const tally = new Map();
    for (const c of changes) {
        const d = domainOf(c.path);
        if (!d) continue;
        tally.set(d, (tally.get(d) ?? 0) + 1);
    }
    if (tally.size === 0) return null;

    const sorted = [...tally.entries()].sort((a, b) => b[1] - a[1]);
    const [topDomain, topCount] = sorted[0];
    // Bỏ qua nhóm "kỹ thuật" nếu còn module nghiệp vụ khác.
    if (['docs', 'ci', 'database', 'tests'].includes(topDomain) && sorted.length > 1) {
        const business = sorted.find(([d]) => !['docs', 'ci', 'database', 'tests'].includes(d));
        if (business && business[1] >= topCount * 0.5) return business[0];
    }
    return topDomain;
}

/**
 * @param {Change[]} changes
 */
function detectScope(changes) {
    const domain = dominantDomain(changes);
    if (domain && domain !== 'docs' && domain !== 'ci') {
        return domain;
    }
    const paths = changes.map((c) => c.path);
    if (paths.every(isFrontend)) return 'frontend';
    if (paths.every(isBackend)) return 'backend';
    return domain; // 'docs' | 'ci' | null
}

// ── Subject ───────────────────────────────────────────────────────────

const GENERIC_BASENAMES = /^(index|main|app|types?|utils?|helpers?|constants?|config|init|README|CHANGELOG)$/i;

function totalChurn(changes) {
    return changes.reduce(
        (acc, c) => ({ added: acc.added + c.added, deleted: acc.deleted + c.deleted }),
        { added: 0, deleted: 0 },
    );
}

/** File "đầu tàu": churn lớn nhất, ưu tiên file mới và file code. */
function headlineFile(changes) {
    const ranked = [...changes].sort((a, b) => {
        const codeA = isFrontend(a.path) || isBackend(a.path) ? 1 : 0;
        const codeB = isFrontend(b.path) || isBackend(b.path) ? 1 : 0;
        if (codeA !== codeB) return codeB - codeA;
        const newA = a.status.startsWith('A') ? 1 : 0;
        const newB = b.status.startsWith('A') ? 1 : 0;
        if (newA !== newB) return newB - newA;
        return b.added + b.deleted - (a.added + a.deleted);
    });
    return ranked[0] ?? null;
}

function pickVerb(type, changes) {
    const added = changes.filter((c) => c.status.startsWith('A')).length;
    const deleted = changes.filter((c) => c.status.startsWith('D')).length;
    const modified = changes.length - added - deleted;
    if (type === 'fix') return 'fix';
    if (type === 'refactor') return 'refactor';
    if (type === 'perf') return 'improve';
    if (type === 'style') return 'format';
    if (added > 0 && modified === 0 && deleted === 0) return 'add';
    if (deleted > 0 && added === 0 && modified === 0) return 'remove';
    return 'update';
}

/**
 * @param {string} type
 * @param {Change[]} changes
 * @param {string|null} scope
 */
function buildSubject(type, changes, scope) {
    const verb = pickVerb(type, changes);
    const head = headlineFile(changes);

    // Ưu tiên: humanize tên file đầu tàu nếu có ý nghĩa.
    if (head && (isFrontend(head.path) || isBackend(head.path))) {
        const base = head.path.split('/').pop();
        const stem = base.replace(/\.[a-z0-9]+$/i, '');
        if (!GENERIC_BASENAMES.test(stem)) {
            let phrase = humanize(base)
                .replace(/\b(controller|request|resource|use case|usecase|service|store|composable|tab|modal|page)\b/gi, '')
                .replace(/\s+/g, ' ')
                .trim();
            // Bỏ tiền tố trùng scope cho gọn (vd scope=coaching, phrase="coaching session ...").
            if (scope && phrase.startsWith(`${scope.replace(/-/g, ' ')} `)) {
                phrase = phrase.slice(scope.replace(/-/g, ' ').length).trim();
            }
            if (phrase.length >= 3) {
                const noun = describeKind(head.path);
                return `${verb} ${phrase}${noun ? ` ${noun}` : ''}`.trim();
            }
        }
    }

    // Fallback: theo các area thay đổi.
    const areas = [...new Set(changes.map((c) => areaOf(c.path)))].slice(0, 3);
    const areaText = areas.join(', ') || 'project files';
    return `${verb} ${areaText}`;
}

/** Danh từ loại file để subject tự nhiên hơn ("... controller", "... component"). */
function describeKind(path) {
    if (/Controller\.php$/.test(path)) return 'controller';
    if (/Request\.php$/.test(path)) return 'request';
    if (/Resource\.php$/.test(path)) return 'resource';
    if (/UseCase\.php$/.test(path)) return 'use case';
    if (/\.vue$/.test(path) && /components\//.test(path)) return 'component';
    if (/\.vue$/.test(path) && /Pages\//.test(path)) return 'page';
    if (/^use[A-Z].*\.js$/.test(path.split('/').pop() ?? '')) return 'composable';
    if (/database\/migrations\//.test(path)) return 'migration';
    return '';
}

// ── Body (churn stats theo module) ───────────────────────────────────

function churnTag(added, deleted) {
    if (!added && !deleted) return '';
    return ` (+${added}/-${deleted})`;
}

/**
 * @param {Change[]} changes
 */
function buildBody(changes) {
    if (changes.length <= 1) return '';

    /** @type {Map<string, Change[]>} */
    const groups = new Map();
    for (const c of changes) {
        const key = domainOf(c.path) ?? areaOf(c.path);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(c);
    }

    const ranked = [...groups.entries()].sort((a, b) => b[1].length - a[1].length);
    const lines = [];
    const shown = ranked.slice(0, 5);

    for (const [name, group] of shown) {
        const { added, deleted } = totalChurn(group);
        const samples = group
            .filter((c) => isFrontend(c.path) || isBackend(c.path))
            .slice(0, 2)
            .map((c) => c.path.split('/').pop());
        const sampleText = samples.length ? ` — ${samples.join(', ')}` : '';
        let line = `- ${name}: ${group.length} file(s)${churnTag(added, deleted)}${sampleText}`;
        if (line.length > 100) line = line.slice(0, 97) + '…';
        lines.push(line);
    }

    if (ranked.length > shown.length) {
        const rest = ranked.slice(shown.length).reduce((n, [, g]) => n + g.length, 0);
        lines.push(`- … +${rest} file(s) khác`);
    }

    const { added, deleted } = totalChurn(changes);
    lines.push('');
    lines.push(`Tổng: ${changes.length} files changed, +${added}/-${deleted}.`);

    return lines.join('\n');
}

// ── Public API ────────────────────────────────────────────────────────

/**
 * @returns {{ header: string, body: string }|null}
 */
export function generateCommitMessageWithBody() {
    const changes = getStagedChanges();
    if (changes.length === 0) return null;

    const type = detectType(changes);
    if (!type) return null;

    const scope = detectScope(changes);
    const subject = buildSubject(type, changes, scope);

    const headerRaw = scope ? `${type}(${scope}): ${subject}` : `${type}: ${subject}`;
    const header = headerRaw.length > 72 ? `${headerRaw.slice(0, 69)}...` : headerRaw;

    const body = buildBody(changes)
        .split('\n')
        .filter((line) => line.length <= 100)
        .join('\n')
        .trim();

    return { header, body };
}

/**
 * @returns {string|null}
 */
export function generateCommitMessage() {
    const result = generateCommitMessageWithBody();
    if (!result) return null;
    return result.body ? `${result.header}\n\n${result.body}` : result.header;
}

if (process.argv[1]?.endsWith('generate-commit-msg.mjs')) {
    const message = generateCommitMessage();
    if (message) {
        process.stdout.write(message);
    }
    process.exit(message ? 0 : 1);
}
