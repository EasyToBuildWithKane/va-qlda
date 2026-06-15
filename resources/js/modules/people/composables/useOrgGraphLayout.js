import { computed, toValue } from 'vue';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

/**
 * Pure layout engine for the interactive organization graph (Option A).
 *
 * Turns the org-team forest into an absolute-positioned node/edge model using a
 * classic top-down "tidy tree" pass. The hierarchy rendered is:
 *
 *   (synthetic org hub, only when >1 root Nhóm)
 *     └─ team (cấp quản lý / đơn vị) ── leader on card
 *          ├─ nhánh & thành viên (section / person, when expanded) — above sub-units
 *          └─ đơn vị con (team)
 *
 * Everything here is geometry + real data — no fabricated metrics.
 */

export const NODE = {
    org: { w: 212, h: 86 },
    team: { w: 234, h: 100 },
    section: { w: 184, h: 68 },
    person: { w: 188, h: 76 },
};
const H_GAP = 28;
const V_GAP = 66;
const PADDING = 56;

const UNASSIGNED_SECTION_KEY = '__unassigned__';

function personFromMember(m, team, rootId, rootName) {
    const emp = m?.employee;
    if (!emp?.id) {
        return null;
    }
    return {
        employeeId: emp.id,
        name: emp.name,
        avatar: emp.avatar_path ?? null,
        roleTitle: emp.role_title ?? null,
        email: emp.email ?? null,
        code: emp.code ?? null,
        isActive: emp.is_active !== false,
        isLeader: false,
        sectionTitle: m.section?.title ?? null,
        branchLabel: m.branch?.label ?? null,
        teamId: team.id,
        teamName: team.name,
        rootId,
        rootName,
    };
}

/**
 * Nhánh / mảng giữa nhóm và thành viên — gồm mảng đã khai báo (kể cả chưa có người).
 * @returns {Array<{ key: string, sectionId: number|null, title: string, people: object[] }>}
 */
function sectionBranches(team, rootId, rootName) {
    const leaderId = team.leader?.id ?? null;
    const sections = toIterableList(team.sections)
        .slice()
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

    /** @type {Map<number|string, { key: string, sectionId: number|null, title: string, people: object[] }>} */
    const byKey = new Map();

    for (const sec of sections) {
        if (!sec?.id) continue;
        byKey.set(sec.id, {
            key: `section-${team.id}-${sec.id}`,
            sectionId: sec.id,
            title: (sec.title ?? '').trim() || 'Nhánh',
            people: [],
        });
    }

    for (const m of toIterableList(team.members)) {
        const emp = m?.employee;
        if (!emp?.id || emp.id === leaderId) continue;

        const sectionId = m.section?.id ?? m.section_id ?? null;
        let bucket;
        if (sectionId != null && byKey.has(sectionId)) {
            bucket = byKey.get(sectionId);
        } else if (sectionId != null) {
            const title = (m.section?.title ?? '').trim() || 'Nhánh';
            bucket = {
                key: `section-${team.id}-orphan-${sectionId}`,
                sectionId,
                title,
                people: [],
            };
            byKey.set(sectionId, bucket);
        } else {
            if (!byKey.has(UNASSIGNED_SECTION_KEY)) {
                byKey.set(UNASSIGNED_SECTION_KEY, {
                    key: `section-${team.id}-unassigned`,
                    sectionId: null,
                    title: 'Chưa phân mảng',
                    people: [],
                });
            }
            bucket = byKey.get(UNASSIGNED_SECTION_KEY);
        }
        const p = personFromMember(m, team, rootId, rootName);
        if (p) bucket.people.push(p);
    }

    const ordered = [];
    for (const sec of sections) {
        if (!sec?.id) continue;
        const row = byKey.get(sec.id);
        if (row) {
            ordered.push(row);
            byKey.delete(sec.id);
        }
    }
    for (const row of byKey.values()) {
        ordered.push(row);
    }

    return ordered;
}

/** Thành viên chưa gán mảng — không chèn thẻ «Chưa phân mảng» trên sơ đồ. */
function isUnassignedBranch(branch) {
    return branch.sectionId == null && String(branch.key).includes('unassigned');
}

function directCount(team) {
    const leaderId = team.leader?.id ?? null;
    let n = leaderId ? 1 : 0;
    for (const m of toIterableList(team.members)) {
        const id = m?.employee?.id;
        if (id && id !== leaderId) n += 1;
    }

    return n;
}

function subtreeCount(team) {
    let n = directCount(team);
    for (const child of toIterableList(team.children)) {
        n += subtreeCount(child);
    }

    return n;
}

/**
 * Build the logical (un-positioned) tree of layout nodes.
 */
function buildLogical(trees, { expanded, collapsed }) {
    const roots = toIterableList(trees);

    const makeTeam = (team, rootId, rootName) => {
        const ln = {
            id: `team-${team.id}`,
            type: 'team',
            team,
            rootId,
            rootName,
            directCount: directCount(team),
            peopleCount: subtreeCount(team),
            expanded: expanded.has(team.id),
            collapsed: collapsed.has(team.id),
            hasSubteams: toIterableList(team.children).length > 0,
            children: [],
        };
        const branches = sectionBranches(team, rootId, rootName);
        ln.hasMembers = branches.length > 0;

        if (ln.expanded) {
            for (const branch of branches) {
                if (isUnassignedBranch(branch)) {
                    for (const p of branch.people) {
                        ln.children.push({
                            id: `person-${team.id}-${p.employeeId}`,
                            type: 'person',
                            person: p,
                            rootId,
                            children: [],
                        });
                    }
                    continue;
                }

                const sectionLn = {
                    id: branch.key,
                    type: 'section',
                    sectionTitle: branch.title,
                    sectionId: branch.sectionId,
                    teamId: team.id,
                    peopleCount: branch.people.length,
                    rootId,
                    children: [],
                };
                for (const p of branch.people) {
                    sectionLn.children.push({
                        id: `person-${team.id}-${p.employeeId}`,
                        type: 'person',
                        person: p,
                        rootId,
                        children: [],
                    });
                }
                ln.children.push(sectionLn);
            }
        }
        if (!ln.collapsed) {
            for (const sub of toIterableList(team.children)) {
                ln.children.push(makeTeam(sub, rootId, rootName));
            }
        }

        return ln;
    };

    const rootNodes = roots.map((t) => makeTeam(t, t.id, t.name));

    if (rootNodes.length <= 1) {
        return rootNodes[0] ?? null;
    }

    return {
        id: 'org-root',
        type: 'org',
        rootCount: rootNodes.length,
        peopleCount: rootNodes.reduce((s, r) => s + r.peopleCount, 0),
        children: rootNodes,
    };
}

function sizeOf(ln) {
    return NODE[ln.type] ?? NODE.team;
}

function shiftSubtree(ln, dx) {
    ln.cx += dx;
    for (const c of ln.children) shiftSubtree(c, dx);
}

function subtreeBottom(ln) {
    let bottom = ln.y + ln.h;
    for (const c of ln.children) {
        bottom = Math.max(bottom, subtreeBottom(c));
    }

    return bottom;
}

/** Top-down tidy placement; returns the horizontal width consumed. */
function place(ln, leftX, top) {
    const { w, h } = sizeOf(ln);
    ln.w = w;
    ln.h = h;
    ln.y = top;
    const kids = ln.children;

    if (!kids.length) {
        ln.cx = leftX + w / 2;
        return w;
    }

    const childTop = top + h + V_GAP;

    if (ln.type === 'team') {
        const subteams = kids.filter((k) => k.type === 'team');
        const sections = kids.filter((k) => k.type === 'section');
        const directPeople = kids.filter((k) => k.type === 'person');
        const memberRow = [...sections, ...directPeople];
        const rest = kids.filter((k) => k.type !== 'team' && k.type !== 'section' && k.type !== 'person');

        if (subteams.length && memberRow.length) {
            let cursor = leftX;
            memberRow.forEach((kid, i) => {
                cursor += place(kid, cursor, childTop);
                if (i < memberRow.length - 1) cursor += H_GAP;
            });
            const row1Span = cursor - leftX;

            let row2Top = childTop;
            for (const mr of memberRow) {
                row2Top = Math.max(row2Top, subtreeBottom(mr) + V_GAP);
            }

            cursor = leftX;
            subteams.forEach((kid, i) => {
                cursor += place(kid, cursor, row2Top);
                if (i < subteams.length - 1) cursor += H_GAP;
            });
            const row2Span = cursor - leftX;

            let row2Bottom = row2Top;
            for (const kid of subteams) {
                row2Bottom = Math.max(row2Bottom, subtreeBottom(kid));
            }

            const restTop = row2Bottom + V_GAP;
            let restCursor = leftX;
            rest.forEach((kid) => {
                restCursor += H_GAP;
                restCursor += place(kid, restCursor, restTop);
            });

            const span = Math.max(row1Span, row2Span, restCursor - leftX);
            const cxCandidates = [];
            if (memberRow.length) {
                cxCandidates.push((memberRow[0].cx + memberRow[memberRow.length - 1].cx) / 2);
            }
            if (subteams.length) {
                cxCandidates.push((subteams[0].cx + subteams[subteams.length - 1].cx) / 2);
            }
            ln.cx = cxCandidates.length
                ? cxCandidates.reduce((a, b) => a + b, 0) / cxCandidates.length
                : leftX + w / 2;

            if (w > span) {
                const shift = (w - span) / 2;
                for (const kid of kids) shiftSubtree(kid, shift);
                ln.cx = leftX + w / 2;
                return w;
            }

            return span;
        }
    }

    let cursor = leftX;
    kids.forEach((kid, i) => {
        cursor += place(kid, cursor, childTop);
        if (i < kids.length - 1) cursor += H_GAP;
    });

    const span = cursor - leftX;
    ln.cx = (kids[0].cx + kids[kids.length - 1].cx) / 2;

    if (w > span) {
        const shift = (w - span) / 2;
        for (const kid of kids) shiftSubtree(kid, shift);
        ln.cx = leftX + w / 2;
        return w;
    }

    return span;
}

/* ------------------------------ filtering ------------------------------- */

function normalize(s) {
    return (s ?? '').toString().toLowerCase().trim();
}

function personMatches(p, f) {
    if (f.rootId && p.rootId !== f.rootId) return false;
    if (f.role === 'leaders' && !p.isLeader) return false;
    if (f.role === 'members' && p.isLeader) return false;
    if (f.status === 'active' && !p.isActive) return false;
    if (f.status === 'inactive' && p.isActive) return false;
    if (f.query) {
        const hay = normalize([p.name, p.roleTitle, p.sectionTitle, p.branchLabel, p.code, p.email].join(' '));
        if (!hay.includes(f.query)) return false;
    }

    return true;
}

function sectionSelfMatches(ln, f) {
    if (f.rootId && ln.rootId !== f.rootId) return false;
    if (f.query && normalize(ln.sectionTitle).includes(f.query)) return true;

    return false;
}


function teamSelfMatches(ln, f) {
    const team = ln.team;
    if (f.rootId && ln.rootId !== f.rootId) return false;
    if (f.status === 'active' && team.is_active === false) return false;
    if (f.status === 'inactive' && team.is_active !== false) return false;
    // A team is never *self*-matched by a people-only role filter.
    if (f.role !== 'all' && !f.query && !f.rootId && f.status === 'all') return false;
    if (f.query && !normalize(team.name).includes(f.query)) return false;

    return true;
}

/** Post-order: mark self match + whether the subtree contains any match. */
function markMatches(ln, f, active) {
    let self = false;
    if (ln.type === 'person') self = personMatches(ln.person, f);
    else if (ln.type === 'section') self = sectionSelfMatches(ln, f);
    else if (ln.type === 'team') self = teamSelfMatches(ln, f);

    let subtree = self;
    for (const c of ln.children) {
        subtree = markMatches(c, f, active) || subtree;
    }
    // The synthetic hub stays lit whenever anything matches.
    if (ln.type === 'org') self = false;

    ln.matched = self;
    ln.inPath = active ? subtree || ln.type === 'org' : true;

    return subtree;
}

function orthogonalDown(x1, y1, x2, y2) {
    const drop = Math.min(22, Math.max(10, (y2 - y1) * 0.28));
    const midY = y1 + drop;
    if (Math.abs(x1 - x2) < 1.5) {
        return `M ${x1} ${y1} L ${x1} ${y2}`;
    }

    return `M ${x1} ${y1} L ${x1} ${midY} L ${x2} ${midY} L ${x2} ${y2}`;
}

function edgePath(x1, y1, x2, y2, kind) {
    if (kind === 'section-member' || kind === 'team-member' || kind === 'team-child') {
        return orthogonalDown(x1, y1, x2, y2);
    }

    const my = (y1 + y2) / 2;
    return `M ${x1} ${y1} C ${x1} ${my} ${x2} ${my} ${x2} ${y2}`;
}

function flatten(ln, nodes, edges, parent) {
    nodes.push(ln);
    if (parent) {
        let kind = 'default';
        if (parent.type === 'section' && ln.type === 'person') {
            kind = 'section-member';
        } else if (parent.type === 'team' && ln.type === 'person') {
            kind = 'team-member';
        } else if (parent.type === 'team' && ln.type === 'team') {
            kind = 'team-child';
        }
        edges.push({
            id: `${parent.id}__${ln.id}`,
            from: parent.id,
            to: ln.id,
            kind,
            x1: parent.cx,
            y1: parent.y + parent.h,
            x2: ln.cx,
            y2: ln.y,
            inPath: ln.inPath && parent.inPath,
        });
    }
    for (const c of ln.children) flatten(c, nodes, edges, ln);
}

/**
 * @returns {{ nodes: object[], edges: object[], width: number, height: number, matchCount: number, hasFilter: boolean }}
 */
export function computeOrgGraph(trees, options = {}) {
    const expanded = options.expanded instanceof Set ? options.expanded : new Set(options.expanded ?? []);
    const collapsed = options.collapsed instanceof Set ? options.collapsed : new Set(options.collapsed ?? []);
    const f = {
        query: normalize(options.query),
        rootId: options.rootId ?? null,
        role: options.role ?? 'all',
        status: options.status ?? 'all',
    };
    const hasFilter = Boolean(f.query) || f.rootId != null || f.role !== 'all' || f.status !== 'all';

    const root = buildLogical(trees, { expanded, collapsed });
    if (!root) {
        return { nodes: [], edges: [], width: 0, height: 0, matchCount: 0, hasFilter };
    }

    place(root, 0, 0);
    markMatches(root, f, hasFilter);

    const nodes = [];
    const edges = [];
    flatten(root, nodes, edges, null);

    let minX = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (const n of nodes) {
        minX = Math.min(minX, n.cx - n.w / 2);
        maxX = Math.max(maxX, n.cx + n.w / 2);
        maxY = Math.max(maxY, n.y + n.h);
    }

    const dx = PADDING - minX;
    for (const n of nodes) {
        n.cx += dx;
        n.x = n.cx - n.w / 2;
        n.revealKey = n.id;
    }
    for (const e of edges) {
        e.x1 += dx;
        e.x2 += dx;
        e.path = edgePath(e.x1, e.y1, e.x2, e.y2, e.kind);
    }

    const matchCount = hasFilter ? nodes.filter((n) => n.matched).length : 0;

    return {
        nodes,
        edges,
        width: maxX - minX + PADDING * 2,
        height: maxY + PADDING * 2,
        matchCount,
        hasFilter,
    };
}

/**
 * Reactive wrapper around {@link computeOrgGraph}.
 *
 * @param {import('vue').MaybeRefOrGetter<object[]>} trees
 * @param {import('vue').MaybeRefOrGetter<object>} state
 */
export function useOrgGraphLayout(trees, state) {
    return computed(() => computeOrgGraph(toValue(trees), toValue(state)));
}
