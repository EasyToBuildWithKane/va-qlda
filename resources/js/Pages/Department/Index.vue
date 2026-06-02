<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import DepartmentFormModal from '@/Components/Project/DepartmentFormModal.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    departments: { type: Object, required: true },
    employees:   { type: Array,  default: () => [] },
    can:         { type: Object, default: () => ({}) },
});

const dialog  = useDialog();
const modal   = ref(false);
const editing = ref(null);
const open    = (d = null) => { editing.value = d; modal.value = true; };

const swatch = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500',
    violet: 'bg-violet-500', amber: 'bg-amber-500', rose: 'bg-rose-500',
    cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};

// ── Cột hiển thị ────────────────────────────────────────────────────────────
const COLS = [
    { key: 'manager',  label: 'Trưởng phòng' },
    { key: 'projects', label: 'Số dự án'     },
    { key: 'status',   label: 'Trạng thái'   },
];
const colVisible = reactive({ manager: true, projects: true, status: true });
const visibleColCount = computed(() => COLS.filter(c => colVisible[c.key]).length);
// 2 fixed cols (phòng ban + thao tác) + visible toggleable
const totalCols = computed(() => 2 + visibleColCount.value);

// ── Tìm kiếm & Bộ lọc ───────────────────────────────────────────────────────
const searchQuery  = ref('');
const statusFilter = ref('all'); // 'all' | 'active' | 'inactive'

const STATUS_OPTS = [
    { key: 'all',      label: 'Tất cả'    },
    { key: 'active',   label: 'Hoạt động' },
    { key: 'inactive', label: 'Ngừng'     },
];

const activeCount   = computed(() => props.departments.data.filter(d =>  d.is_active).length);
const inactiveCount = computed(() => props.departments.data.filter(d => !d.is_active).length);

const statusCount = (key) => ({
    all:      props.departments.data.length,
    active:   activeCount.value,
    inactive: inactiveCount.value,
}[key] ?? 0);

const filtered = computed(() => {
    let list = props.departments.data;
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(d =>
            d.name.toLowerCase().includes(q) ||
            (d.code ?? '').toLowerCase().includes(q) ||
            (d.manager?.name ?? '').toLowerCase().includes(q),
        );
    }
    if (statusFilter.value === 'active')   list = list.filter(d =>  d.is_active);
    if (statusFilter.value === 'inactive') list = list.filter(d => !d.is_active);
    return list;
});

const activeFilterCount = computed(() => (statusFilter.value !== 'all' ? 1 : 0));
const hasAnyFilter      = computed(() => activeFilterCount.value > 0 || searchQuery.value.trim() !== '');
const clearAll = async () => {
    if (!hasAnyFilter.value) return;
    if (!await dialog.confirm({
        title: 'Xoá bộ lọc',
        message: 'Xoá tất cả bộ lọc đang áp dụng?',
        confirmText: 'Xoá lọc',
    })) return;
    statusFilter.value = 'all';
    searchQuery.value = '';
};

// ── Dropdown state ──────────────────────────────────────────────────────────
const showFilterDd = ref(false);
const showColDd    = ref(false);
const filterDdRef  = ref(null);
const colDdRef     = ref(null);

const onDocClick = (e) => {
    if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterDd.value = false;
    if (colDdRef.value    && !colDdRef.value.contains(e.target))    showColDd.value    = false;
};
onMounted(()   => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

const openFilter = () => { showFilterDd.value = !showFilterDd.value; showColDd.value = false; };
const openCol    = () => { showColDd.value    = !showColDd.value;    showFilterDd.value = false; };

// ── Thao tác ─────────────────────────────────────────────────────────────────
const remove = async (d) => {
    const msg = d.project_count
        ? `Xoá "${d.name}"? ${d.project_count} dự án sẽ bị bỏ gán phòng ban (không xoá dự án).`
        : `Xoá phòng ban "${d.name}"?`;
    if (await dialog.confirm({ title: 'Xoá phòng ban', message: msg, tone: 'danger', confirmText: 'Xoá' })) {
        router.delete(`/departments/${d.id}`, { preserveScroll: true });
    }
};

const toggleStatus = async (d) => {
    const toActive = !d.is_active;
    const confirmed = await dialog.confirm({
        title:       toActive ? 'Kích hoạt phòng ban' : 'Ngừng hoạt động',
        message:     toActive
            ? `Kích hoạt "${d.name}"? Phòng ban sẽ xuất hiện khi tạo dự án mới.`
            : `Ngừng hoạt động "${d.name}"? Phòng ban sẽ bị ẩn khi tạo dự án mới.`,
        confirmText: toActive ? 'Kích hoạt' : 'Ngừng',
        tone:        toActive ? 'default'   : 'danger',
    });
    if (!confirmed) return;
    router.patch(`/departments/${d.id}/toggle`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Phòng ban" />
    <AppLayout>
        <template #header>
            <PageHeader
                title="Quản lý phòng ban"
                subtitle="Cơ cấu tổ chức và phân công nhân sự"
                icon="department"
                icon-color="sky"
                :badge="departments.data?.length"
            />
        </template>

        <div class="card overflow-visible">

            <!-- ══ 1. Tiêu đề & nút Thêm ══════════════════════════════════════ -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold text-slate-700">Danh sách phòng ban</h2>
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand/10 px-1.5 text-[11px] font-bold text-brand">
                        {{ departments.data.length }}
                    </span>
                </div>
                <button v-if="can.create" class="btn-primary gap-1.5 text-sm" @click="open()">
                    <AppIcon name="add" :size="15" />
                    Thêm phòng ban
                </button>
            </div>

            <!-- ══ 2. Thanh tìm kiếm + Bộ lọc + Chọn cột ════════════════════ -->
            <div class="flex flex-col gap-2.5 border-b border-slate-100 bg-slate-50/40 px-5 py-3.5 sm:flex-row sm:items-center">

                <!-- Search (co giãn) -->
                <div class="relative flex-1">
                    <AppIcon
                        name="search"
                        :size="15"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="input h-9 w-full pl-9 pr-8 text-sm placeholder:text-slate-400"
                        placeholder="Tìm theo tên, mã, trưởng phòng…"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        @click="searchQuery = ''"
                    >
                        <AppIcon name="close" :size="14" />
                    </button>
                </div>

                <!-- Right controls -->
                <div class="flex shrink-0 items-center gap-2">

                    <!-- ── Bộ lọc dropdown ── -->
                    <div ref="filterDdRef" class="relative">
                        <button
                            type="button"
                            class="flex h-9 items-center gap-1.5 rounded-btn border px-3 text-sm font-medium transition select-none"
                            :class="showFilterDd || activeFilterCount > 0
                                ? 'border-brand/40 bg-brand/5 text-brand'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                            @click="openFilter"
                        >
                            <AppIcon name="filter" :size="14" />
                            Bộ lọc
                            <!-- badge khi có filter đang chọn -->
                            <span
                                v-if="activeFilterCount > 0"
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-white"
                            >{{ activeFilterCount }}</span>
                            <AppIcon name="chevron-down" :size="13" class="opacity-50" :class="showFilterDd && 'rotate-180'" style="transition: transform .15s" />
                        </button>

                        <!-- Dropdown panel -->
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 -translate-y-1"
                            leave-active-class="transition duration-100 ease-in"
                            leave-to-class="opacity-0 scale-95 -translate-y-1"
                        >
                            <div
                                v-if="showFilterDd"
                                class="absolute right-0 top-full z-30 mt-1.5 w-64 origin-top-right rounded-xl border border-slate-200 bg-white shadow-elevation-2"
                            >
                                <!-- Header -->
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                                    <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Bộ lọc</span>
                                    <button
                                        v-if="activeFilterCount > 0"
                                        type="button"
                                        class="text-xs text-brand hover:underline"
                                        @click="statusFilter = 'all'"
                                    >Xoá</button>
                                </div>

                                <!-- Trạng thái -->
                                <div class="px-4 py-3">
                                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Trạng thái</p>
                                    <div class="flex flex-col gap-1">
                                        <label
                                            v-for="opt in STATUS_OPTS"
                                            :key="opt.key"
                                            class="flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition hover:bg-slate-50"
                                            :class="statusFilter === opt.key ? 'bg-brand/5' : ''"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <span
                                                    class="flex h-4 w-4 items-center justify-center rounded-full border-2 transition"
                                                    :class="statusFilter === opt.key
                                                        ? 'border-brand bg-brand'
                                                        : 'border-slate-300'"
                                                >
                                                    <span v-if="statusFilter === opt.key" class="h-1.5 w-1.5 rounded-full bg-white" />
                                                </span>
                                                <span class="text-sm" :class="statusFilter === opt.key ? 'font-semibold text-slate-800' : 'text-slate-600'">
                                                    {{ opt.label }}
                                                </span>
                                            </div>
                                            <span class="text-[11px] font-medium text-slate-400">{{ statusCount(opt.key) }}</span>
                                            <input
                                                type="radio"
                                                :value="opt.key"
                                                v-model="statusFilter"
                                                class="sr-only"
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- ── Cột hiển thị dropdown ── -->
                    <div ref="colDdRef" class="relative">
                        <button
                            type="button"
                            class="flex h-9 items-center gap-1.5 rounded-btn border px-3 text-sm font-medium transition select-none"
                            :class="showColDd
                                ? 'border-brand/40 bg-brand/5 text-brand'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
                            @click="openCol"
                        >
                            <AppIcon name="columns" :size="14" />
                            Cột hiển thị
                            <AppIcon name="chevron-down" :size="13" class="opacity-50" :class="showColDd && 'rotate-180'" style="transition: transform .15s" />
                        </button>

                        <!-- Dropdown panel -->
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 -translate-y-1"
                            leave-active-class="transition duration-100 ease-in"
                            leave-to-class="opacity-0 scale-95 -translate-y-1"
                        >
                            <div
                                v-if="showColDd"
                                class="absolute right-0 top-full z-30 mt-1.5 w-52 origin-top-right rounded-xl border border-slate-200 bg-white shadow-elevation-2"
                            >
                                <div class="border-b border-slate-100 px-4 py-2.5">
                                    <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
                                </div>
                                <!-- Fixed cols (always on) -->
                                <div class="px-4 py-2">
                                    <div
                                        v-for="fixedLabel in ['Phòng ban', 'Thao tác']"
                                        :key="fixedLabel"
                                        class="flex items-center justify-between rounded-lg px-2 py-1.5 opacity-50"
                                    >
                                        <span class="text-sm text-slate-600">{{ fixedLabel }}</span>
                                        <AppIcon name="check" :size="14" class="text-emerald-500" />
                                    </div>
                                </div>
                                <div class="border-t border-slate-100 px-4 py-2">
                                    <label
                                        v-for="col in COLS"
                                        :key="col.key"
                                        class="flex cursor-pointer items-center justify-between rounded-lg px-2 py-1.5 transition hover:bg-slate-50"
                                    >
                                        <span class="text-sm" :class="colVisible[col.key] ? 'text-slate-800 font-medium' : 'text-slate-500'">
                                            {{ col.label }}
                                        </span>
                                        <!-- Toggle pill -->
                                        <span
                                            class="relative inline-flex h-4 w-7 rounded-full transition-colors duration-200"
                                            :class="colVisible[col.key] ? 'bg-brand' : 'bg-slate-200'"
                                        >
                                            <span
                                                class="absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition-transform duration-200"
                                                :class="colVisible[col.key] ? 'translate-x-3.5' : 'translate-x-0.5'"
                                            />
                                        </span>
                                        <input type="checkbox" v-model="colVisible[col.key]" class="sr-only" />
                                    </label>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- ══ 3. Active filter chips ════════════════════════════════════ -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div v-if="hasAnyFilter" class="flex items-center gap-2 flex-wrap border-b border-slate-100 bg-slate-50/30 px-5 py-2">
                    <span class="text-xs text-slate-400 font-medium">Đang lọc:</span>

                    <!-- Search chip -->
                    <span
                        v-if="searchQuery.trim()"
                        class="inline-flex items-center gap-1.5 rounded-full border border-brand/20 bg-brand/5 px-2.5 py-0.5 text-xs font-medium text-brand"
                    >
                        <AppIcon name="search" :size="11" />
                        "{{ searchQuery.trim() }}"
                        <button type="button" class="ml-0.5 hover:text-brand/60" @click="searchQuery = ''">
                            <AppIcon name="close" :size="11" />
                        </button>
                    </span>

                    <!-- Status chip -->
                    <span
                        v-if="statusFilter !== 'all'"
                        class="inline-flex items-center gap-1.5 rounded-full border border-brand/20 bg-brand/5 px-2.5 py-0.5 text-xs font-medium text-brand"
                    >
                        {{ STATUS_OPTS.find(o => o.key === statusFilter)?.label }}
                        <button type="button" class="ml-0.5 hover:text-brand/60" @click="statusFilter = 'all'">
                            <AppIcon name="close" :size="11" />
                        </button>
                    </span>

                    <button
                        type="button"
                        class="ml-auto text-xs text-slate-400 hover:text-slate-600 flex items-center gap-1 transition"
                        @click="clearAll"
                    >
                        <AppIcon name="close" :size="11" /> Xoá tất cả
                    </button>
                </div>
            </Transition>

            <!-- ══ 4. Table ══════════════════════════════════════════════════ -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Phòng ban</th>
                            <th v-if="colVisible.manager"  class="px-5 py-3">Trưởng phòng</th>
                            <th v-if="colVisible.projects" class="px-5 py-3 text-center">Dự án</th>
                            <th v-if="colVisible.status"   class="px-5 py-3 text-center">Trạng thái</th>
                            <th class="px-5 py-3 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="d in filtered"
                            :key="d.id"
                            class="group transition-colors hover:bg-slate-50/70"
                        >
                            <!-- Phòng ban -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                        :class="swatch[d.color] ?? swatch.slate"
                                    >
                                        <AppIcon name="department" :size="16" class="text-white/90" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800 truncate leading-snug">{{ d.name }}</p>
                                        <p class="font-mono text-[11px] tracking-wider text-slate-400">{{ d.code }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Trưởng phòng -->
                            <td v-if="colVisible.manager" class="px-5 py-3.5">
                                <div v-if="d.manager" class="flex items-center gap-2">
                                    <Avatar :name="d.manager.name" :src="d.manager.avatar_path" :size="26" />
                                    <span class="text-sm text-slate-700 truncate">{{ d.manager.name }}</span>
                                </div>
                                <span v-else class="text-xs text-slate-300">Chưa phân công</span>
                            </td>

                            <!-- Số dự án -->
                            <td v-if="colVisible.projects" class="px-5 py-3.5 text-center">
                                <span
                                    class="inline-flex min-w-[28px] items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="(d.project_count ?? 0) > 0 ? 'bg-brand/10 text-brand' : 'bg-slate-100 text-slate-400'"
                                >
                                    {{ d.project_count ?? 0 }}
                                </span>
                            </td>

                            <!-- Trạng thái -->
                            <td v-if="colVisible.status" class="px-5 py-3.5 text-center">
                                <button
                                    v-if="d.can?.update"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition hover:shadow-sm"
                                    :class="d.is_active
                                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                                        : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                    :title="d.is_active ? 'Nhấn để ngừng hoạt động' : 'Nhấn để kích hoạt'"
                                    @click="toggleStatus(d)"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="d.is_active ? 'bg-emerald-500' : 'bg-slate-400'" />
                                    {{ d.is_active ? 'Hoạt động' : 'Ngừng' }}
                                </button>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="d.is_active ? 'bg-emerald-500' : 'bg-slate-400'" />
                                    {{ d.is_active ? 'Hoạt động' : 'Ngừng' }}
                                </span>
                            </td>

                            <!-- Thao tác -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        v-if="d.can?.update"
                                        class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                        title="Chỉnh sửa"
                                        @click="open(d)"
                                    >
                                        <AppIcon name="edit" :size="14" />
                                    </button>
                                    <button
                                        v-if="d.can?.delete"
                                        class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                        title="Xoá"
                                        @click="remove(d)"
                                    >
                                        <AppIcon name="delete" :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="filtered.length === 0">
                            <td :colspan="totalCols" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <AppIcon name="department" :size="36" class="text-slate-200" />
                                    <p class="text-sm font-medium text-slate-400">
                                        {{ hasAnyFilter ? 'Không có phòng ban phù hợp với bộ lọc.' : 'Chưa có phòng ban nào.' }}
                                    </p>
                                    <button
                                        v-if="hasAnyFilter"
                                        type="button"
                                        class="mt-1 text-xs text-brand hover:underline"
                                        @click="clearAll"
                                    >Xoá bộ lọc</button>
                                    <button
                                        v-else-if="can.create"
                                        class="btn-primary mt-2 text-xs gap-1"
                                        @click="open()"
                                    >
                                        <AppIcon name="add" :size="13" /> Thêm phòng ban đầu tiên
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ══ 5. Footer ════════════════════════════════════════════════ -->
            <div v-if="filtered.length > 0" class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-5 py-2.5">
                <p class="text-xs text-slate-400">
                    Hiển thị
                    <span class="font-semibold text-slate-600">{{ filtered.length }}</span> /
                    <span class="font-semibold text-slate-600">{{ departments.data.length }}</span> phòng ban
                    <template v-if="activeCount > 0">
                        ·&nbsp;<span class="font-medium text-emerald-600">{{ activeCount }} hoạt động</span>
                    </template>
                    <template v-if="inactiveCount > 0">
                        ·&nbsp;<span class="font-medium text-slate-500">{{ inactiveCount }} ngừng</span>
                    </template>
                </p>
            </div>
        </div>

        <DepartmentFormModal
            :show="modal"
            :department="editing"
            :employees="employees"
            :existing-departments="departments.data"
            @close="modal = false"
        />
    </AppLayout>
</template>
