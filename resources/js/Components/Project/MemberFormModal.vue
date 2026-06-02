<script setup>
import { computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    member: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const roleOptions = [
    { value: 'pm', label: 'Quản lý dự án (PM)' },
    { value: 'lead', label: 'Trưởng nhóm (Lead)' },
    { value: 'developer', label: 'Lập trình viên' },
    { value: 'designer', label: 'Thiết kế (Designer)' },
    { value: 'qa', label: 'Kiểm thử (QA/QC)' },
    { value: 'devops', label: 'DevOps' },
    { value: 'analyst', label: 'Phân tích nghiệp vụ (BA)' },
    { value: 'other', label: 'Khác' },
];

const rateTypeOptions = [
    { value: 'hourly', label: 'Theo giờ' },
    { value: 'monthly', label: 'Theo tháng' },
];

const form = useForm({
    employee_id: null,
    role: 'developer',
    rate_type: 'hourly',
    rate: null,
    allocation: 100,
    joined_at: null,
    is_active: true,
});

const selectedMemberIds = computed(() => new Set((props.members || []).map((m) => m.id)));
const selectableEmployees = computed(() => {
    if (props.member) return props.employees;
    return props.employees.filter((e) => !selectedMemberIds.value.has(e.id));
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.member) {
        form.employee_id = props.member.id;
        form.role = props.member.project_role ?? 'developer';
        form.rate_type = props.member.rate_type ?? 'hourly';
        form.rate = props.member.rate ?? null;
        form.allocation = props.member.allocation ?? 100;
        form.joined_at = props.member.joined_at;
        form.is_active = props.member.is_active;
    } else {
        form.reset();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.member) form.put(`/projects/${props.projectId}/members/${props.member.id}`, opts);
    else form.post(`/projects/${props.projectId}/members`, opts);
};
</script>

<template>
    <Modal :show="show" :dirty="form.isDirty" :title="member ? 'Cập nhật thành viên' : 'Thêm thành viên'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label">Nhân sự <span class="text-rose-500">*</span></label>
                <select v-model="form.employee_id" class="input" :disabled="!!member">
                    <option :value="null">— Chọn nhân sự —</option>
                    <option v-for="e in selectableEmployees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <p v-if="form.errors.employee_id" class="mt-1 text-xs text-danger">{{ form.errors.employee_id }}</p>
                <p v-if="!member && !selectableEmployees.length" class="mt-1 text-xs text-slate-400">Tất cả nhân sự đã nằm trong dự án.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Vai trò <span class="text-rose-500">*</span></label>
                    <select v-model="form.role" class="input">
                        <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <p v-if="form.errors.role" class="mt-1 text-xs text-danger">{{ form.errors.role }}</p>
                </div>
                <div>
                    <label class="label">Kiểu lương <span class="text-rose-500">*</span></label>
                    <select v-model="form.rate_type" class="input">
                        <option v-for="r in rateTypeOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <p v-if="form.errors.rate_type" class="mt-1 text-xs text-danger">{{ form.errors.rate_type }}</p>
                </div>
                <div>
                    <label class="label">Đơn giá</label>
                    <input v-model.number="form.rate" type="number" min="0" step="1000" class="input" placeholder="Ví dụ: 150000" />
                    <p v-if="form.errors.rate" class="mt-1 text-xs text-danger">{{ form.errors.rate }}</p>
                </div>
                <div>
                    <label class="label">
                        Phân bổ (%)
                        <span class="ml-1 cursor-help text-slate-400" title="Tỷ lệ % thời gian làm việc cho dự án này. Mặc định 100%.">ⓘ</span>
                    </label>
                    <input v-model.number="form.allocation" type="number" min="0" max="100" step="5" class="input" placeholder="100" />
                    <p v-if="form.errors.allocation" class="mt-1 text-xs text-danger">{{ form.errors.allocation }}</p>
                </div>
                <div class="col-span-2">
                    <label class="label">Ngày tham gia</label>
                    <input v-model="form.joined_at" type="date" class="input" />
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                <input v-model="form.is_active" type="checkbox" class="rounded" />
                Đang hoạt động trong dự án
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="modalClose()">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ member ? 'Lưu thay đổi' : 'Thêm thành viên' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
