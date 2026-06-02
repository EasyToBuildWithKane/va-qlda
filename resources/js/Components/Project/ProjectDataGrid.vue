<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import { COLUMNS, cellValue } from '@/Components/Project/projectColumns';
import { currency, date } from '@/composables/useFormat';

const props = defineProps({
    projects: { type: Array, default: () => [] },
    visible: { type: Array, default: () => [] }, // visible column keys
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
const colSpan = computed(() => cols.value.length + 2); // name + actions

// ---- Sorting --------------------------------------------------------------
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

// ---- Grouping -------------------------------------------------------------
const groups = computed(() => {
    if (!props.groupByDepartment) return [{ key: 'all', label: null, projects: sorted.value }];
    const out = props.departmentOptions.map((d) => ({
        key: 'd' + d.id, label: d.name, color: d.color,
        projects: sorted.value.filter((p) => p.department_id === d.id),
    }));
    out.push({ key: 'none', label: 'Chưa phân phòng', color: 'slate', projects: sorted.value.filter((p) => !p.department_id) });
    return out.filter((g) => g.projects.length > 0);
});

// Collapsed by default (spec). Track expanded groups instead.
const expanded = ref(new Set());
const isOpen = (g) => g.key === 'all' || expanded.value.has(g.key);
const toggleGroup = (g) => {
    const s = new Set(expanded.value);
    s.has(g.key) ? s.delete(g.key) : s.add(g.key);
    expanded.value = s;
};
</script>

<template>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="sticky left-0 top-0 z-20 min-w-[15rem] border-b border-slate-200 bg-slate-50 px-4 py-2.5 font-medium">
                            <button class="flex items-center gap-1 hover:text-slate-700" @click="toggleSort('name')">
                                Dự án
                                <AppIcon v-if="sort.key === 'name'" :name="sort.dir === 'asc' ? 'chevron-down' : 'chevron-down'" :size="13" :class="sort.dir === 'asc' ? 'rotate-180' : ''" />
                                <AppIcon v-else name="sort" :size="12" class="text-slate-300" />
                            </button>
                        </th>
                        <th
                            v-for="c in cols" :key="c.key"
                            class="sticky top-0 z-10 whitespace-nowrap border-b border-slate-200 bg-slate-50 px-4 py-2.5 font-medium"
                            :class="c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : 'text-left'"
                        >
                            <button class="inline-flex items-center gap-1 hover:text-slate-700" @click="toggleSort(c.key)">
                                {{ c.label }}
                                <AppIcon v-if="sort.key === c.key" name="chevron-down" :size="13" :class="sort.dir === 'asc' ? 'rotate-180' : ''" />
                                <AppIcon v-else name="sort" :size="12" class="text-slate-300" />
                            </button>
                        </th>
                        <th class="sticky right-0 top-0 z-20 border-b border-slate-200 bg-slate-50 px-4 py-2.5"></th>
                    </tr>
                </thead>

                <tbody>
                    <template v-for="g in groups" :key="g.key">
                        <!-- Group header row -->
                        <tr v-if="g.label !== null" class="cursor-pointer bg-slate-100/70 hover:bg-slate-100" @click="toggleGroup(g)">
                            <td :colspan="colSpan" class="sticky left-0 border-b border-slate-200 px-4 py-2">
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <AppIcon name="chevron-down" :size="15" :class="isOpen(g) ? '' : '-rotate-90'" class="text-slate-400 transition-transform" />
                                    <span class="h-2.5 w-2.5 rounded-full" :class="dot[g.color] || dot.slate"></span>
                                    {{ g.label }}
                                    <span class="font-normal text-slate-400">({{ g.projects.length }} dự án)</span>
                                </span>
                            </td>
                        </tr>

                        <!-- Data rows -->
                        <template v-if="isOpen(g)">
                            <tr v-for="p in g.projects" :key="p.id" class="group hover:bg-slate-50">
                                <!-- Sticky name -->
                                <td class="sticky left-0 z-10 min-w-[15rem] border-b border-slate-100 bg-white px-4 py-2.5 group-hover:bg-slate-50">
                                    <div class="flex items-center gap-2">
                                        <span class="h-7 w-1.5 shrink-0 rounded-full" :class="stripe[p.color] || stripe.slate"></span>
                                        <div class="min-w-0">
                                            <p class="font-mono text-[11px] text-slate-400">{{ p.code }}</p>
                                            <Link :href="`/projects/${p.id}`" class="font-medium text-slate-700 hover:text-brand">{{ p.name }}</Link>
                                        </div>
                                    </div>
                                </td>

                                <!-- Dynamic columns -->
                                <td
                                    v-for="c in cols" :key="c.key"
                                    class="whitespace-nowrap border-b border-slate-100 px-4 py-2.5"
                                    :class="c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : 'text-left'"
                                >
                                    <template v-if="c.key === 'code'"><span class="font-mono text-xs text-slate-500">{{ p.code }}</span></template>
                                    <template v-else-if="c.key === 'type'"><Badge v-if="p.type" :label="p.type.label" :color="p.type.color" /></template>
                                    <template v-else-if="c.key === 'scope'"><Badge v-if="p.scope" :label="p.scope.label" :color="p.scope.color" /></template>
                                    <template v-else-if="c.key === 'status'"><Badge v-if="p.status" :label="p.status.label" :color="p.status.color" /></template>
                                    <template v-else-if="c.key === 'department'">
                                        <Badge v-if="p.department" :label="p.department.name" :color="p.department.color" />
                                        <span v-else class="text-slate-400">—</span>
                                    </template>
                                    <template v-else-if="c.key === 'manager'">
                                        <span v-if="p.manager" class="inline-flex items-center gap-1.5">
                                            <Avatar :name="p.manager.name" :src="p.manager.avatar_path" :size="22" />
                                            <span class="text-slate-600">{{ p.manager.name }}</span>
                                        </span>
                                        <span v-else class="text-slate-400">—</span>
                                    </template>
                                    <template v-else-if="c.key === 'progress'"><div class="w-32"><ProgressBar :value="p.progress" /></div></template>
                                    <template v-else-if="c.key === 'budget'"><span class="text-slate-600">{{ currency(p.budget) }}</span></template>
                                    <template v-else-if="c.key === 'actual_budget'"><span class="text-slate-600">{{ currency(p.actual_budget) }}</span></template>
                                    <template v-else-if="c.key === 'labor_cost'"><span class="font-medium text-slate-700">{{ currency(p.labor_cost) }}</span></template>
                                    <template v-else-if="c.key === 'start_date'"><span class="text-slate-500">{{ date(p.start_date) }}</span></template>
                                    <template v-else-if="c.key === 'due_date'"><span class="text-slate-500">{{ date(p.due_date) }}</span></template>
                                    <template v-else-if="c.key === 'created_at'"><span class="text-slate-500">{{ date(p.created_at) }}</span></template>
                                    <template v-else-if="c.key === 'updated_at'"><span class="text-slate-500">{{ date(p.updated_at) }}</span></template>
                                    <template v-else-if="c.key === 'task_count'"><span class="text-slate-600">{{ p.task_count ?? 0 }}</span></template>
                                    <template v-else-if="c.key === 'member_count'"><span class="text-slate-600">{{ p.member_count ?? 0 }}</span></template>
                                    <template v-else-if="c.key === 'open_blocker_count'">
                                        <span :class="p.open_blocker_count ? 'font-semibold text-rose-600' : 'text-slate-400'">{{ p.open_blocker_count ?? 0 }}</span>
                                    </template>
                                </td>

                                <!-- Sticky quick actions -->
                                <td class="sticky right-0 z-10 border-b border-slate-100 bg-white px-3 py-2.5 group-hover:bg-slate-50">
                                    <div class="flex justify-end gap-0.5 opacity-0 transition group-hover:opacity-100">
                                        <Link :href="`/projects/${p.id}`" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Xem"><AppIcon name="eye" :size="15" /></Link>
                                        <Link v-if="p.can?.update" :href="`/projects/${p.id}/edit`" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Sửa"><AppIcon name="edit" :size="15" /></Link>
                                        <button v-if="p.can?.update" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Nhân bản" @click="emit('duplicate', p)"><AppIcon name="copy" :size="15" /></button>
                                        <button v-if="p.can?.delete" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Xoá" @click="emit('remove', p)"><AppIcon name="delete" :size="15" /></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </template>

                    <tr v-if="projects.length === 0">
                        <td :colspan="colSpan" class="px-4 py-12 text-center text-slate-400">Không có dự án nào khớp bộ lọc.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
