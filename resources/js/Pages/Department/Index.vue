<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import DepartmentFormModal from '@/Components/Project/DepartmentFormModal.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    departments: { type: Object, required: true }, // { data: [...] }
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const editing = ref(null);

const open = (d = null) => { editing.value = d; modal.value = true; };

const swatch = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};

const remove = async (d) => {
    const msg = d.project_count
        ? `Xoá "${d.name}"? ${d.project_count} dự án sẽ bị bỏ gán phòng ban (không xoá dự án).`
        : `Xoá "${d.name}"?`;
    if (await dialog.confirm({ title: 'Xoá phòng ban', message: msg, tone: 'danger', confirmText: 'Xoá' })) {
        router.delete(`/departments/${d.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Phòng ban" />
    <AppLayout>
        <template #header>
            <h1 class="font-display font-semibold text-slate-800">Phòng ban</h1>
        </template>

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-slate-500">Đơn vị tổ chức sở hữu các dự án.</p>
            <button v-if="can.create" class="btn-primary" @click="open()"><AppIcon name="add" :size="16" /> Thêm phòng ban</button>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Phòng ban</th>
                        <th class="px-4 py-2 font-medium">Trưởng phòng</th>
                        <th class="px-4 py-2 text-center font-medium">Số dự án</th>
                        <th class="px-4 py-2 text-center font-medium">Trạng thái</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="d in departments.data" :key="d.id" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full" :class="swatch[d.color] || swatch.slate"></span>
                                <div>
                                    <p class="font-mono text-[11px] text-slate-400">{{ d.code }}</p>
                                    <p class="font-medium text-slate-700">{{ d.name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div v-if="d.manager" class="flex items-center gap-1.5">
                                <Avatar :name="d.manager.name" :src="d.manager.avatar_path" :size="24" />
                                <span class="text-slate-600">{{ d.manager.name }}</span>
                            </div>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-center text-slate-600">{{ d.project_count ?? 0 }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                {{ d.is_active ? 'Hoạt động' : 'Ngừng' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="flex justify-end gap-1">
                                <button v-if="d.can?.update" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="open(d)"><AppIcon name="edit" :size="15" /></button>
                                <button v-if="d.can?.delete" class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" @click="remove(d)"><AppIcon name="delete" :size="15" /></button>
                            </span>
                        </td>
                    </tr>
                    <tr v-if="departments.data.length === 0"><td colspan="5" class="px-4 py-12 text-center text-slate-400">Chưa có phòng ban nào.</td></tr>
                </tbody>
            </table>
        </div>

        <DepartmentFormModal :show="modal" :department="editing" :employees="employees" @close="modal = false" />
    </AppLayout>
</template>
