<script setup>
import { inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    task: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    defaultEmployeeId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const today = new Date().toISOString().slice(0, 10);
const form = useForm({ employee_id: null, date: today, hours: 1, note: '' });

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    form.reset();
    form.date = today;
    form.employee_id = props.defaultEmployeeId;
});

const submit = () => {
    if (!props.task) return;
    form.post(`/projects/${props.projectId}/tasks/${props.task.id}/worklogs`, {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    });
};
</script>

<template>
    <Modal :show="show" :dirty="form.isDirty" :title="'Ghi nhận giờ làm' + (task ? ' — ' + task.title : '')" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label">Người thực hiện</label>
                <select v-model="form.employee_id" class="input">
                    <option :value="null">— Chọn —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <p v-if="form.errors.employee_id" class="mt-1 text-xs text-danger">{{ form.errors.employee_id }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Ngày</label>
                    <input v-model="form.date" type="date" class="input" />
                </div>
                <div>
                    <label class="label">Số giờ</label>
                    <input v-model.number="form.hours" type="number" step="0.25" min="0.25" max="24" class="input" />
                    <p v-if="form.errors.hours" class="mt-1 text-xs text-danger">{{ form.errors.hours }}</p>
                </div>
            </div>
            <div>
                <label class="label">Ghi chú</label>
                <input v-model="form.note" type="text" class="input" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="modalClose()">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">Ghi nhận</button>
            </div>
        </form>
    </Modal>
</template>
