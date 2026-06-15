import { computed, toValue } from 'vue';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

/**
 * Pure layout engine for the interactive organization graph (Option A).
 *
 * Turns the org-team forest into an absolute-positioned node/edge model using a
 * classic top-down "tidy tree" pass. The hierarchy rendered is:
 *
 *   (synthetic org hub, only when >1 Ban/Khối)
 *     └─ Ban/Khối (team)  ── leader shown on the card
 *          └─ Nhóm (team)
 *               └─ members (person, only when the team is expanded)
 *
 * Everything here is geometry + real data — no fabricated metrics.
 */

export const NODE = {
    org: { w: 212, h: 86 },
    team: { w: 234, h: 100 },
    person: { w: 188, h: 70 },
};
const H_GAP = 28;
const V_GAP = 66;
const PADDING = 56;

let uid = 0;

function memberPeople(team, rootId, rootName) {
    const leaderId = team.leader?.id ?? null;
    const out = [];
    for (const m of toIterableList(team.members)) {
        const emp = m?.employee;
        if (!emp?.id || emp.id === leaderId) {
            continue;
        }
        out.push({
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
        });
    }

    return out;
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
        ln.hasMembers = memberPeople(team, rootId, rootName).length > 0;

        if (!ln.collapsed) {
            for (const sub of toIterableList(team.children)) {
                ln.children.push(makeTeam(sub, rootId, rootName));
            }
        }
        if (ln.expanded) {
            for (const p of memberPeople(team, rootId, rootName)) {
                ln.children.push({
                    id: `person-${team.id}-${p.employeeId}`,
                    type: 'person',
                    person: p,
                    rootId,
                    children: [],
                });
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
        const hay = normalize([p.name, p.roleTitle, p.code, p.email].join(' '));
        if (!hay.includes(f.query)) return false;
    }

    return true;
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

function flatten(ln, nodes, edges, parent) {
    nodes.push(ln);
    if (parent) {
        edges.push({
            id: `${parent.id}__${ln.id}`,
            from: parent.id,
            to: ln.id,
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

    // Fresh ids each compute so :key churn forces edge re-animation on relayout.
    uid += 1;
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
        n.revealKey = `${uid}-${n.id}`;
    }
    for (const e of edges) {
        e.x1 += dx;
        e.x2 += dx;
        const my = (e.y1 + e.y2) / 2;
        e.path = `M ${e.x1} ${e.y1} C ${e.x1} ${my} ${e.x2} ${my} ${e.x2} ${e.y2}`;
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
