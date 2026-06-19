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

const props = defineProps({
    projects: { type: Array, default: () => [] },
    visible: { type: Array, default: () => [] },
    groupByDepartment: { type: Boolean, default: false },
    departmentOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['remove', 'duplicate']);

const stripe = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};
const dot = stripe;

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

const groups = computed(() => {
    if (!props.groupByDepartment) return [{ key: 'all', label: null, projects: sorted.value }];
    const out = props.departmentOptions.map((d) => ({
        key: 'd' + d.id, label: d.name, color: d.color,
        projects: sorted.value.filter((p) => p.department_id === d.id),
    }));
    out.push({ key: 'none', label: 'Chưa phân phòng', color: 'slate', projects: sorted.value.filter((p) => !p.department_id) });
    return out.filter((g) => g.projects.length > 0);
});

const collapsedGroups = ref(new Set());
const isOpen = (g) => g.key === 'all' || !collapsedGroups.value.has(g.key);
const toggleGroup = (g) => {
    const s = new Set(collapsedGroups.value);
    s.has(g.key) ? s.delete(g.key) : s.add(g.key);
    collapsedGroups.value = s;
};

const progressTone = (v) => {
    if (v >= 100) return 'text-emerald-600';
    if (v >= 50) return 'text-sky-600';
    return 'text-slate-600';
};
</script>

<template>
  <div class="card overflow-hidden">
    <div class="project-grid-scroll overflow-x-auto">
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
          <template
            v-for="g in groups"
            :key="g.key"
          >
            <tr
              v-if="g.label !== null"
              class="cursor-pointer bg-slate-100/70 hover:bg-slate-100"
              @click="toggleGroup(g)"
            >
              <td
                :colspan="colSpan"
                class="border-b border-slate-200 px-3 py-2"
              >
                <span class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                  <AppIcon
                    name="chevron-down"
                    :size="14"
                    :class="isOpen(g) ? '' : '-rotate-90'"
                    class="shrink-0 text-slate-400 transition-transform"
                  />
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    :class="dot[g.color] || dot.slate"
                  />
                  <span class="truncate">{{ g.label }}</span>
                  <span class="shrink-0 font-normal text-slate-400">({{ g.projects.length }})</span>
                </span>
              </td>
            </tr>

            <template v-if="isOpen(g)">
              <tr
                v-for="(p, rowIndex) in g.projects"
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
                      class="text-slate-300"
                    >—</span>
                  </template>
                  <template v-else-if="c.key === 'category'">
                    <Badge
                      v-if="p.category"
                      :label="p.category.label"
                      :color="p.category.color"
                    />
                    <span
                      v-else
                      class="text-slate-300"
                    >—</span>
                  </template>
                  <template v-else-if="c.key === 'scope'">
                    <Badge
                      v-if="p.scope"
                      :label="p.scope.label"
                      :color="p.scope.color"
                    />
                    <span
                      v-else
                      class="text-slate-300"
                    >—</span>
                  </template>
                  <template v-else-if="c.key === 'status'">
                    <Badge
                      v-if="p.status"
                      :label="p.status.label"
                      :color="p.status.color"
                    />
                    <span
                      v-else
                      class="text-slate-300"
                    >—</span>
                  </template>
                  <template v-else-if="c.key === 'department'">
                    <Badge
                      v-if="p.department"
                      :label="p.department.name"
                      :color="p.department.color"
                    />
                    <span
                      v-else
                      class="text-slate-300"
                    >—</span>
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
                      class="text-slate-300"
                    >—</span>
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
            </template>
          </template>

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
