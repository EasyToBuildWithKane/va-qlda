<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/Components/Project/Badge.vue';
import BlockerFormModal from '@/Components/Project/BlockerFormModal.vue';
import { date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    blockers: { type: Object, required: true }, // { data: [...] }
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const editing = ref(null);

const open = (b = null) => { editing.value = b; modal.value = true; };

const filterForm = reactive({
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    mine: props.filters.mine ? '1' : '',
});

watch(filterForm, () => {
    router.get('/blockers', {
        status: filterForm.status || undefined,
        severity: filterForm.severity || undefined,
        project_id: filterForm.project_id || undefined,
        mine: filterForm.mine || undefined,
    }, { preserveState: true, replace: true });
});

const resolve = (b) => router.put(`/blockers/${b.id}`, { status: 'resolved' }, { preserveScroll: true });
const remove = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Vướng mắc" />
    <AppLayout>
        <template #header>
            <h1 class="font-display font-semibold text-slate-800">Vướng mắc cần xử lý</h1>
        </template>

        <div class="mb-5 grid grid-cols-3 gap-4">
            <div class="card p-4"><p class="text-sm text-slate-500">Đang mở</p><p class="mt-1 font-display text-2xl font-bold text-rose-500">{{ summary.open ?? 0 }}</p></div>
            <div class="card p-4"><p class="text-sm text-slate-500">Nghiêm trọng</p><p class="mt-1 font-display text-2xl font-bold text-amber-600">{{ summary.critical ?? 0 }}</p></div>
            <div class="card p-4"><p class="text-sm text-slate-500">Đã xử lý</p><p class="mt-1 font-display text-2xl font-bold text-emerald-600">{{ summary.resolved ?? 0 }}</p></div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <select v-model="filterForm.status" class="input w-40">
                <option value="">Mọi trạng thái</option>
                <option v-for="o in options.status" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <select v-model="filterForm.severity" class="input w-40">
                <option value="">Mọi mức độ</option>
                <option v-for="o in options.severity" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <select v-model="filterForm.project_id" class="input w-52">
                <option value="">Mọi dự án</option>
                <option v-for="p in options.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="filterForm.mine" true-value="1" false-value="" type="checkbox" class="rounded" /> Tôi xử lý
            </label>
            <button v-if="can.create" class="btn-primary ml-auto" @click="open()"><AppIcon name="add" :size="16" /> Ghi nhận</button>
        </div>

        <div class="space-y-3">
            <div v-for="b in blockers.data" :key="b.id" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :label="b.severity.label" :color="b.severity.color" />
                            <Badge :label="b.status.label" :color="b.status.color" />
                            <span v-if="b.project" class="text-xs font-medium text-slate-400">{{ b.project.name }}</span>
                            <span class="font-medium text-slate-800">{{ b.title }}</span>
                        </div>
                        <p v-if="b.description" class="mt-1 text-sm text-slate-500">{{ b.description }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ date(b.raised_at) }} · Báo bởi {{ b.raised_by?.name || '—' }}
                            <span v-if="b.owner"> · Xử lý: {{ b.owner.name }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button v-if="b.can?.update && b.status.value !== 'resolved'" class="btn-ghost text-xs text-emerald-600" @click="resolve(b)">
                            <AppIcon name="done" :size="14" /> Đã xử lý
                        </button>
                        <button v-if="b.can?.update" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="open(b)"><AppIcon name="edit" :size="15" /></button>
                        <button v-if="b.can?.delete" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" @click="remove(b)"><AppIcon name="delete" :size="15" /></button>
                    </div>
                </div>
            </div>
            <p v-if="!blockers.data.length" class="rounded-card border border-dashed border-slate-200 py-16 text-center text-sm text-slate-400">Không có vướng mắc nào.</p>
        </div>

        <BlockerFormModal
            :show="modal" :blocker="editing"
            :projects="options.projects" :employees="options.employees"
            :severity-options="options.severity" :status-options="options.status"
            @close="modal = false"
        />
    </AppLayout>
</template>
