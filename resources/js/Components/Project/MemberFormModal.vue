<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    member: { type: Object, default: null }, // existing member (with id = employee id)
    employees: { type: Array, default: () => [] },
    rateTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    employee_id: null, role: 'developer', rate_type: 'hourly', rate: null,
    allocation: 100, joined_at: null, is_active: true,
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.member) {
        form.employee_id = props.member.id;
        form.role = props.member.project_role ?? 'developer';
        form.rate_type = props.member.rate_type ?? 'hourly';
        form.rate = props.member.rate;
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
    <Modal :show="show" :title="member ? 'Cập nhật thành viên' : 'Thêm thành viên'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label">Nhân sự</label>
                <select v-model="form.employee_id" class="input" :disabled="!!member">
                    <option :value="null">— Chọn —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <p v-if="form.errors.employee_id" class="mt-1 text-xs text-danger">{{ form.errors.employee_id }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Vai trò</label>
                    <input v-model="form.role" type="text" class="input" placeholder="developer / qa / pm" />
                </div>
                <div>
                    <label class="label">Phân bổ (%)</label>
                    <input v-model.number="form.allocation" type="number" min="0" max="100" class="input" />
                </div>
                <div>
                    <label class="label">Loại lương</label>
                    <select v-model="form.rate_type" class="input">
                        <option v-for="o in rateTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Mức lương (₫)</label>
                    <input v-model.number="form.rate" type="number" min="0" step="1000" class="input" />
                </div>
                <div class="col-span-2">
                    <label class="label">Ngày tham gia</label>
                    <input v-model="form.joined_at" type="date" class="input" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.is_active" type="checkbox" class="rounded" /> Đang hoạt động
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="emit('close')">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ member ? 'Lưu' : 'Thêm' }}</button>
            </div>
        </form>
    </Modal>
</template>
