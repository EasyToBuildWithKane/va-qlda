<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { COLUMNS, cellValue } from '@/modules/project/config/columns';
import { currency, date } from '@/composables/useFormat';
import ProjectListRowActions from '@/modules/project/components/ProjectListRowActions.vue';
import ProjectMembers from '@/modules/project/components/ProjectMembers.vue';
import { PROJECT_COLOR_SWATCH } from '@/modules/project/utils/projectColors';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    projects: { type: Array, default: () => [] },
    visible: { type: Array, default: () => [] },
});

const emit = defineEmits(['remove', 'duplicate']);

const stripe = PROJECT_COLOR_SWATCH;

const cols = computed(() => COLUMNS.filter((c) => props.visible.includes(c.key)));
const colSpan = computed(() => cols.value.length + 2);

const sort = ref({ key: null, dir: 'asc' });
const toggleSort = (key) => {
    if (sort.value.key === key) sort.value.dir = sort.value.dir === 'asc' ? 'desc' : 'asc';
    else sort.value = { key, dir: 'asc' };
};

const sorted = computed(() => {
    const list = [...props.projects];
    const { key, dir } = sort.value;
    if (!key) return list;
    const sign = dir === 'asc' ? 1 : -1;
    return list.sort((a, b) => {
        const va = key === 'name' ? a.name : cellValue(a, key);
        const vb = key === 'name' ? b.name : cellValue(b, key);
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * sign;
        return String(va).localeCompare(String(vb), 'vi') * sign;
    });
});

const progressTone = (v) => {
    if (v >= 100) return 'text-emerald-600';
    if (v >= 50) return 'text-sky-600';
    return 'text-slate-600';
};

// Drag-to-scroll (horizontal) — hạn chế cuộn trang khi kéo ngang bảng rộng
const scrollRef = ref(null);
const dragging = ref(false);
let pointerId = null;
let startX = 0;
let startScrollLeft = 0;
let moved = false;
let suppressClick = false;
const DRAG_THRESHOLD = 8;

function isInteractiveTarget(target) {
    return !!target?.closest?.(
        'a, button, input, select, textarea, label, [role="button"], [data-no-drag-scroll]',
    );
}

function onPointerDown(e) {
    const el = scrollRef.value;
    if (!el || e.button !== 0 || e.pointerType !== 'mouse') return;
    if (isInteractiveTarget(e.target)) return;

    pointerId = e.pointerId;
    startX = e.clientX;
    startScrollLeft = el.scrollLeft;
    moved = false;
    suppressClick = false;
    dragging.value = false;
}

function onPointerMove(e) {
    const el = scrollRef.value;
    if (!el || pointerId !== e.pointerId) return;

    const dx = e.clientX - startX;
    if (!moved && Math.abs(dx) < DRAG_THRESHOLD) return;

    if (!moved) {
        moved = true;
        suppressClick = true;
        dragging.value = true;
        try {
            el.setPointerCapture(e.pointerId);
        } catch {
            /* ignore */
        }
    }

    el.scrollLeft = startScrollLeft - dx;
    e.preventDefault();
}

function endPointer(e) {
    const el = scrollRef.value;
    if (pointerId == null || (e?.pointerId != null && pointerId !== e.pointerId)) return;

    if (el?.hasPointerCapture?.(pointerId)) {
        try {
            el.releasePointerCapture(pointerId);
        } catch {
            /* ignore */
        }
    }

    const wasDragging = suppressClick;
    pointerId = null;
    dragging.value = false;
    moved = false;

    if (wasDragging) {
        setTimeout(() => {
            suppressClick = false;
        }, 0);
    } else {
        suppressClick = false;
    }
}

function onClickCapture(e) {
    if (!suppressClick) return;
    e.preventDefault();
    e.stopPropagation();
}
</script>

<template>
  <div class="card overflow-hidden">
    <div
      ref="scrollRef"
      class="project-grid-scroll overflow-x-auto"
      :class="{ 'project-grid-scroll--dragging': dragging }"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="endPointer"
      @pointercancel="endPointer"
      @lostpointercapture="endPointer"
      @click.capture="onClickCapture"
    >
      <table class="project-grid w-max min-w-full table-auto border-separate border-spacing-0 text-sm">
        <colgroup>
          <col class="min-w-[13rem]">
          <col
            v-for="c in cols"
            :key="c.key"
            :class="c.colClass"
          >
          <col class="min-w-[7.5rem]">
        </colgroup>
        <thead>
          <tr class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <th class="border-b border-slate-200 px-3 py-2.5">
              <button
                class="flex items-center gap-1 whitespace-nowrap hover:text-slate-700"
                @click="toggleSort('name')"
              >
                Dự án
                <AppIcon
                  v-if="sort.key === 'name'"
                  name="chevron-down"
                  :size="12"
                  :class="sort.dir === 'asc' ? 'rotate-180' : ''"
                />
                <AppIcon
                  v-else
                  name="sort"
                  :size="11"
                  class="shrink-0 text-slate-300"
                />
              </button>
            </th>
            <th
              v-for="c in cols"
              :key="c.key"
              class="border-b border-slate-200 px-3 py-2.5"
              :class="c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : 'text-left'"
            >
              <button
                class="inline-flex items-center gap-1 whitespace-nowrap hover:text-slate-700"
                :class="c.align === 'right' ? 'ml-auto' : ''"
                @click="toggleSort(c.key)"
              >
                {{ c.label }}
                <AppIcon
                  v-if="sort.key === c.key"
                  name="chevron-down"
                  :size="12"
                  class="shrink-0"
                  :class="sort.dir === 'asc' ? 'rotate-180' : ''"
                />
                <AppIcon
                  v-else
                  name="sort"
                  :size="11"
                  class="shrink-0 text-slate-300"
                />
              </button>
            </th>
            <th class="border-b border-slate-200 px-3 py-2.5 text-right">
              <span class="whitespace-nowrap">Thao tác</span>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(p, rowIndex) in sorted"
            :key="p.id"
            class="project-grid-row group hover:bg-slate-50/80"
            :style="{ '--row-delay': `${rowIndex * 35}ms` }"
          >
            <td class="whitespace-nowrap border-b border-slate-100 px-3 py-2.5">
              <div class="flex items-center gap-2">
                <span
                  class="h-7 w-1 shrink-0 rounded-full"
                  :class="stripe[p.color] || stripe.slate"
                />
                <div>
                  <p class="font-mono text-[11px] leading-tight text-slate-400">
                    {{ p.code }}
                  </p>
                  <Link
                    :href="`/projects/${p.id}`"
                    class="block font-medium leading-snug text-slate-700 hover:text-brand"
                  >
                    {{ p.name }}
                  </Link>
                </div>
              </div>
            </td>

            <td
              v-for="c in cols"
              :key="c.key"
              class="whitespace-nowrap border-b border-slate-100 px-3 py-2.5"
              :class="c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : 'text-left'"
            >
              <template v-if="c.key === 'code'">
                <span class="block font-mono text-[11px] text-slate-500">{{ p.code }}</span>
              </template>
              <template v-else-if="c.key === 'type'">
                <Badge
                  v-if="p.type"
                  :label="p.type.label"
                  :color="p.type.color"
                />
                <span
                  v-else
                  class="text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </template>
              <template v-else-if="c.key === 'category'">
                <Badge
                  v-if="p.category"
                  :label="p.category.label"
                  :color="p.category.color"
                />
                <span
                  v-else
                  class="text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </template>
              <template v-else-if="c.key === 'scope'">
                <Badge
                  v-if="p.scope"
                  :label="p.scope.label"
                  :color="p.scope.color"
                />
                <span
                  v-else
                  class="text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </template>
              <template v-else-if="c.key === 'status'">
                <Badge
                  v-if="p.status"
                  :label="p.status.label"
                  :color="p.status.color"
                />
                <span
                  v-else
                  class="text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </template>
              <template v-else-if="c.key === 'department'">
                <div
                  v-if="p.department || (p.related_departments || []).length"
                  class="flex flex-wrap items-center gap-1"
                >
                  <Badge
                    v-if="p.department"
                    :label="p.department.name"
                    :color="p.department.color"
                  />
                  <Badge
                    v-for="d in (p.related_departments || []).slice(0, 2)"
                    :key="'rd-'+d.id"
                    :label="d.name"
                    :color="d.color"
                  />
                  <span
                    v-if="(p.related_departments || []).length > 2"
                    class="text-[11px] font-medium text-slate-400"
                  >+{{ p.related_departments.length - 2 }} liên đới</span>
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.team) }}</span>
              </template>
              <template v-else-if="c.key === 'manager'">
                <span
                  v-if="p.manager"
                  class="inline-flex items-center gap-1.5"
                >
                  <Avatar
                    :name="p.manager.name"
                    :src="p.manager.avatar_path"
                    :size="22"
                    class="shrink-0"
                  />
                  <span class="text-sm text-slate-600">{{ p.manager.name }}</span>
                </span>
                <span
                  v-else
                  class="text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </template>
              <template v-else-if="c.key === 'progress'">
                <span
                  class="text-sm font-semibold tabular-nums"
                  :class="progressTone(p.progress ?? 0)"
                >{{ p.progress ?? 0 }}%</span>
              </template>
              <template v-else-if="c.key === 'budget'">
                <span class="block text-xs text-slate-600">{{ currency(p.budget) }}</span>
              </template>
              <template v-else-if="c.key === 'actual_budget'">
                <span class="block text-xs text-slate-600">{{ currency(p.actual_budget) }}</span>
              </template>
              <template v-else-if="c.key === 'labor_cost'">
                <span class="block text-xs font-medium text-slate-700">{{ currency(p.labor_cost) }}</span>
              </template>
              <template v-else-if="c.key === 'start_date'">
                <span class="text-sm tabular-nums text-slate-500">{{ date(p.start_date) }}</span>
              </template>
              <template v-else-if="c.key === 'due_date'">
                <span class="text-sm tabular-nums text-slate-500">{{ date(p.due_date) }}</span>
              </template>
              <template v-else-if="c.key === 'created_at'">
                <span class="text-sm tabular-nums text-slate-500">{{ date(p.created_at) }}</span>
              </template>
              <template v-else-if="c.key === 'updated_at'">
                <span class="text-sm tabular-nums text-slate-500">{{ date(p.updated_at) }}</span>
              </template>
              <template v-else-if="c.key === 'task_count'">
                <span class="text-sm font-medium tabular-nums text-slate-600">{{ p.task_count ?? 0 }}</span>
              </template>
              <template v-else-if="c.key === 'member_count'">
                <ProjectMembers
                  v-if="Array.isArray(p.members) && p.members.length"
                  :members="p.members"
                  :max-visible="4"
                  :max-name-labels="3"
                  show-names
                  compact
                />
                <span
                  v-else
                  class="text-xs text-slate-400"
                >Chưa có thành viên</span>
              </template>
              <template v-else-if="c.key === 'open_blocker_count'">
                <span
                  class="text-sm font-semibold tabular-nums"
                  :class="p.open_blocker_count ? 'text-rose-600' : 'text-slate-400'"
                >{{ p.open_blocker_count ?? 0 }}</span>
              </template>
            </td>

            <td class="whitespace-nowrap border-b border-slate-100 px-2 py-2.5 text-right">
              <ProjectListRowActions
                :project="p"
                @duplicate="emit('duplicate', $event)"
                @remove="emit('remove', $event)"
              />
            </td>
          </tr>

          <tr v-if="projects.length === 0">
            <td
              :colspan="colSpan"
              class="px-4 py-12 text-center text-slate-400"
            >
              Không có dự án nào khớp bộ lọc.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.project-grid-scroll {
    -webkit-overflow-scrolling: touch;
    cursor: grab;
    overscroll-behavior-x: contain;
}

.project-grid-scroll--dragging {
    cursor: grabbing;
    user-select: none;
}

.project-grid-scroll--dragging :deep(a),
.project-grid-scroll--dragging :deep(button) {
    pointer-events: none;
}

.project-grid-row {
    animation: project-row-in 0.4s ease backwards;
    animation-delay: var(--row-delay, 0ms);
}

@keyframes project-row-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .project-grid-row {
        animation: none;
    }
}
</style>
