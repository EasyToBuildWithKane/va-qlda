<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import OrgGraphNode from './OrgGraphNode.vue';
import { useOrgGraphLayout } from '@/modules/people/composables/useOrgGraphLayout.js';
import { useGraphViewport } from '@/modules/people/composables/useGraphViewport.js';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    trees: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({ query: '', rootId: null, role: 'all', status: 'all' }) },
});

const emit = defineEmits(['select-person', 'select-leader']);

const viewportRef = ref(null);
const vp = useGraphViewport({ minScale: 0.35, maxScale: 1.8 });

const expanded = ref(new Set());
const collapsed = ref(new Set());

function defaultExpandedRootIds() {
    const ids = new Set();
    const walk = (team) => {
        const leaderId = team.leader?.id ?? null;
        const hasBranchMembers = toIterableList(team.members).some(
            (m) => m?.employee?.id && m.employee.id !== leaderId,
        );
        const hasSections = toIterableList(team.sections).length > 0;
        const hasSubteams = toIterableList(team.children).length > 0;
        if (team.level === 1 && hasSubteams && (hasSections || hasBranchMembers)) {
            ids.add(team.id);
        }
        for (const c of toIterableList(team.children)) {
            walk(c);
        }
    };
    for (const root of toIterableList(props.trees)) {
        walk(root);
    }

    return ids;
}

function syncDefaultExpansion() {
    const defaults = defaultExpandedRootIds();
    if (!defaults.size) {
        return;
    }
    const next = new Set(expanded.value);
    for (const id of defaults) {
        next.add(id);
    }
    expanded.value = next;
}

/** Team ids that actually have non-leader members (used for "expand all"). */
const memberTeamIds = computed(() => {
    const ids = new Set();
    const walk = (team) => {
        const leaderId = team.leader?.id ?? null;
        const hasMembers = toIterableList(team.members).some((m) => m?.employee?.id && m.employee.id !== leaderId);
        const hasSections = toIterableList(team.sections).length > 0;
        if (hasMembers || hasSections) ids.add(team.id);
        for (const c of toIterableList(team.children)) walk(c);
    };
    for (const root of toIterableList(props.trees)) walk(root);
    return ids;
});

const filterActive = computed(() => Boolean(props.filter.query)
    || props.filter.rootId != null
    || props.filter.role !== 'all'
    || props.filter.status !== 'all');

const graph = useOrgGraphLayout(
    () => props.trees,
    () => ({
        expanded: filterActive.value ? memberTeamIds.value : expanded.value,
        collapsed: filterActive.value ? new Set() : collapsed.value,
        query: props.filter.query,
        rootId: props.filter.rootId,
        role: props.filter.role,
        status: props.filter.status,
    }),
);

const canvasStyle = computed(() => ({
    width: `${graph.value.width}px`,
    height: `${graph.value.height}px`,
    transform: vp.transform.value,
}));

/* ---- toggles ---- */
function toggleMembers(node) {
    const next = new Set(expanded.value);
    if (next.has(node.team.id)) next.delete(node.team.id);
    else next.add(node.team.id);
    expanded.value = next;
}
function toggleSubteams(node) {
    const next = new Set(collapsed.value);
    if (next.has(node.team.id)) next.delete(node.team.id);
    else next.add(node.team.id);
    collapsed.value = next;
}
function expandAll() {
    expanded.value = new Set(memberTeamIds.value);
    collapsed.value = new Set();
}
function collapseAll() {
    expanded.value = new Set();
    const rootsWithChildren = new Set();
    for (const root of toIterableList(props.trees)) {
        if (toIterableList(root.children).length) rootsWithChildren.add(root.id);
    }
    collapsed.value = rootsWithChildren;
}

/* ---- viewport fitting ---- */
let fittedOnce = false;
function fit() {
    vp.fitTo(graph.value.width, graph.value.height, { maxInitialScale: 1, padding: 24 });
}
onMounted(() => {
    vp.bindViewport(viewportRef.value);
    syncDefaultExpansion();
    nextTick(fit);
});
watch(() => props.trees, () => {
    fittedOnce = false;
    syncDefaultExpansion();
    nextTick(() => {
        if (!fittedOnce) {
            fit();
            fittedOnce = true;
        }
    });
});

// Center on the first match when filtering.
watch(
    () => [props.filter.query, props.filter.rootId, props.filter.role, props.filter.status],
    () => {
        if (!filterActive.value) return;
        nextTick(() => {
            const hit = graph.value.nodes.find((n) => n.matched);
            if (hit) vp.centerOn(hit.cx, hit.y + hit.h / 2);
        });
    },
);
</script>

<template>
  <div class="org-graph">
    <!-- top toolbar -->
    <div class="org-graph__toolbar">
      <div class="org-graph__cluster">
        <button
          type="button"
          class="org-graph__btn"
          @click="expandAll"
        >
          <AppIcon
            name="plus"
            :size="13"
          />
          Mở tất cả
        </button>
        <button
          type="button"
          class="org-graph__btn"
          @click="collapseAll"
        >
          <AppIcon
            name="minus"
            :size="13"
          />
          Thu gọn
        </button>
      </div>
      <p
        v-if="graph.hasFilter"
        class="org-graph__matchbadge"
      >
        <AppIcon
          name="filter"
          :size="12"
        />
        {{ graph.matchCount }} kết quả
      </p>
    </div>

    <div
      ref="viewportRef"
      class="org-graph__viewport"
      :class="{ 'is-panning': vp.isPanning.value }"
      @wheel="vp.onWheel"
      @pointerdown="vp.onPointerDown"
    >
      <div
        class="org-graph__ambient"
        aria-hidden="true"
      >
        <div class="org-graph__grid" />
        <div class="org-graph__orb org-graph__orb--brand" />
        <div class="org-graph__orb org-graph__orb--sky" />
      </div>

      <div
        class="org-graph__canvas"
        :style="canvasStyle"
      >
        <OrgGraphNode
          v-for="(node, i) in graph.nodes"
          :key="node.revealKey"
          :node="node"
          :index="i"
          @toggle-members="toggleMembers"
          @toggle-subteams="toggleSubteams"
          @select-person="emit('select-person', $event)"
          @select-leader="emit('select-leader', $event)"
        />

        <svg
          v-if="graph.edges.length"
          class="org-graph__edges"
          :width="graph.width"
          :height="graph.height"
          :viewBox="`0 0 ${graph.width} ${graph.height}`"
        >
          <defs>
            <linearGradient
              id="org-edge-grad"
              x1="0"
              y1="0"
              x2="0"
              y2="1"
            >
              <stop
                offset="0"
                stop-color="#94a3b8"
                stop-opacity="0.9"
              />
              <stop
                offset="1"
                stop-color="#cbd5e1"
                stop-opacity="0.75"
              />
            </linearGradient>
          </defs>
          <path
            v-for="edge in graph.edges"
            :key="edge.id"
            class="org-graph__edge"
            :class="{
              'org-graph__edge--dim': edge.inPath === false,
              'org-graph__edge--orthogonal': edge.kind === 'section-member'
                || edge.kind === 'team-member'
                || edge.kind === 'team-child'
                || edge.kind === 'management-unit',
            }"
            :d="edge.path"
          />
        </svg>
      </div>

      <!-- zoom controls -->
      <div class="org-graph__zoom">
        <button
          type="button"
          class="org-graph__zoombtn"
          aria-label="Phóng to"
          @click="vp.zoomIn"
        >
          <AppIcon
            name="plus"
            :size="16"
          />
        </button>
        <span class="org-graph__zoomval">{{ Math.round(vp.scale.value * 100) }}%</span>
        <button
          type="button"
          class="org-graph__zoombtn"
          aria-label="Thu nhỏ"
          @click="vp.zoomOut"
        >
          <AppIcon
            name="minus"
            :size="16"
          />
        </button>
        <button
          type="button"
          class="org-graph__zoombtn"
          aria-label="Vừa màn hình"
          @click="fit"
        >
          <AppIcon
            name="grid"
            :size="15"
          />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.org-graph {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.org-graph__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.org-graph__cluster {
    display: inline-flex;
    gap: 0.4rem;
}

.org-graph__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    height: 32px;
    padding: 0 0.7rem;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.4);
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.org-graph__btn:hover {
    color: #9a0036;
    border-color: rgba(154, 0, 54, 0.4);
    background: rgba(154, 0, 54, 0.04);
}

.org-graph__matchbadge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 11.5px;
    font-weight: 600;
    color: #9a0036;
}

.org-graph__viewport {
    position: relative;
    height: clamp(460px, 68vh, 760px);
    overflow: hidden;
    border-radius: 20px;
    border: 1px solid rgba(148, 163, 184, 0.3);
    background:
        radial-gradient(120% 80% at 50% -10%, #f8fafc 0%, #eef2f7 55%, #e9edf3 100%);
    cursor: grab;
    touch-action: none;
    user-select: none;
}

.org-graph__viewport.is-panning {
    cursor: grabbing;
}

.org-graph__ambient {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.org-graph__grid {
    position: absolute;
    inset: -50%;
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.16) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.16) 1px, transparent 1px);
    background-size: 30px 30px;
    mask-image: radial-gradient(70% 60% at 50% 40%, #000 0%, transparent 80%);
}

.org-graph__orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(70px);
    opacity: 0.5;
}

.org-graph__orb--brand {
    top: -60px;
    left: 12%;
    width: 320px;
    height: 320px;
    background: rgba(154, 0, 54, 0.18);
}

.org-graph__orb--sky {
    bottom: -80px;
    right: 14%;
    width: 280px;
    height: 280px;
    background: rgba(56, 132, 255, 0.14);
}

.org-graph__canvas {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    will-change: transform;
}

.org-graph__canvas :deep(.org-node) {
    pointer-events: auto;
}

.org-graph__edges {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2;
    overflow: visible;
    pointer-events: none;
}

.org-graph__edge {
    fill: none;
    stroke: url(#org-edge-grad);
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    opacity: 0;
    animation: org-edge-in 0.45s ease forwards;
}

.org-graph__edge--orthogonal {
    stroke: #94a3b8;
    stroke-width: 2;
    stroke-opacity: 1;
}

.org-graph__edge--dim {
    opacity: 0.18;
    animation: none;
}

@keyframes org-edge-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .org-graph__edge {
        animation: none;
        opacity: 1;
    }
}

.org-graph__zoom {
    position: absolute;
    right: 14px;
    bottom: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 6px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(148, 163, 184, 0.35);
    box-shadow: 0 10px 24px -16px rgba(15, 23, 42, 0.5);
}

.org-graph__zoombtn {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    color: #475569;
    transition: background 0.2s ease, color 0.2s ease;
}

.org-graph__zoombtn:hover {
    background: rgba(154, 0, 54, 0.08);
    color: #9a0036;
}

.org-graph__zoomval {
    font-size: 10px;
    font-weight: 700;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
</style>
