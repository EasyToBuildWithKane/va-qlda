<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import BugFormModal from '@/Components/Project/BugFormModal.vue';

const props = defineProps({
    bugs: { type: Object, required: true }, // { data, meta, links }
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const modal = ref(false);

const filterForm = reactive({
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    mine: props.filters.mine ? '1' : '',
    q: props.filters.q ?? '',
});

let timer = null;
watch(filterForm, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/bugs', {
            status: filterForm.status || undefined,
            severity: filterForm.severity || undefined,
            project_id: filterForm.project_id || undefined,
            mine: filterForm.mine || undefined,
            q: filterForm.q || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
});
</script>

<template>
    <Head title="Quản lý lỗi" />
    <AppLayout>
        <template #header>
            <PageHeader
                title="Quản lý lỗi (Bug)"
                subtitle="Theo dõi và xử lý các vấn đề kỹ thuật"
                icon="bug"
                icon-color="rose"
                :badge="summary.open ?? null"
            />
        </template>

        <div class="mb-5 grid grid-cols-3 gap-4">
            <div class="card p-4"><p class="text-sm text-slate-500">Đang mở</p><p class="mt-1 font-display text-2xl font-bold text-rose-500">{{ summary.open ?? 0 }}</p></div>
            <div class="card p-4"><p class="text-sm text-slate-500">Nghiêm trọng</p><p class="mt-1 font-display text-2xl font-bold text-amber-600">{{ summary.critical ?? 0 }}</p></div>
            <div class="card p-4"><p class="text-sm text-slate-500">Đã sửa</p><p class="mt-1 font-display text-2xl font-bold text-emerald-600">{{ summary.resolved ?? 0 }}</p></div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative">
                <AppIcon name="search" :size="16" class="pointer-events-none absolute left-2.5 top-2.5 text-slate-400" />
                <input v-model="filterForm.q" type="text" placeholder="Tìm bug…" class="input w-52 pl-8" />
            </div>
            <select v-model="filterForm.status" class="input w-36">
                <option value="">Trạng thái</option>
                <option v-for="o in options.status" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <select v-model="filterForm.severity" class="input w-36">
                <option value="">Mức độ</option>
                <option v-for="o in options.severity" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <select v-model="filterForm.project_id" class="input w-48">
                <option value="">Mọi dự án</option>
                <option v-for="p in options.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="filterForm.mine" true-value="1" false-value="" type="checkbox" class="rounded" /> Tôi sửa
            </label>
            <button v-if="can.create" class="btn-primary ml-auto" @click="modal = true"><AppIcon name="add" :size="16" /> Báo lỗi</button>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Mã</th>
                        <th class="px-4 py-2 font-medium">Tiêu đề</th>
                        <th class="px-4 py-2 font-medium">Dự án</th>
                        <th class="px-4 py-2 font-medium">Mức độ</th>
                        <th class="px-4 py-2 font-medium">Trạng thái</th>
                        <th class="px-4 py-2 font-medium">Người sửa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in bugs.data" :key="b.id" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ b.code }}</td>
                        <td class="px-4 py-2.5">
                            <Link :href="`/bugs/${b.id}`" class="font-medium text-slate-700 hover:text-brand">{{ b.title }}</Link>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ b.project?.name }}</td>
                        <td class="px-4 py-2.5"><Badge :label="b.severity.label" :color="b.severity.color" /></td>
                        <td class="px-4 py-2.5"><Badge :label="b.status.label" :color="b.status.color" /></td>
                        <td class="px-4 py-2.5">
                            <div v-if="b.assignee" class="flex items-center gap-1.5">
                                <Avatar :name="b.assignee.name" :src="b.assignee.avatar_path" :size="22" />
                                <span class="text-slate-600">{{ b.assignee.name }}</span>
                            </div>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="!bugs.data.length"><td colspan="6" class="px-4 py-12 text-center text-slate-400">Không có bug nào.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="bugs.meta && bugs.meta.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="(link, i) in bugs.meta.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-btn px-3 py-1.5 text-sm"
                    :class="link.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100'"
                    v-html="link.label"
                />
            </template>
        </div>

        <BugFormModal
            :show="modal" :projects="options.projects" :employees="options.employees"
            :severity-options="options.severity" :status-options="options.status" :priority-options="options.priority"
            @close="modal = false"
        />
    </AppLayout>
</template>
