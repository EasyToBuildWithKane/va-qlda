<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    sprint: { type: Object, default: null },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({ name: '', goal: '', status: 'planned', start_date: null, end_date: null, sort_order: 0 });

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.sprint) {
        form.name = props.sprint.name;
        form.goal = props.sprint.goal ?? '';
        form.status = props.sprint.status.value;
        form.start_date = props.sprint.start_date;
        form.end_date = props.sprint.end_date;
        form.sort_order = props.sprint.sort_order;
    } else {
        form.reset();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.sprint) form.put(`/projects/${props.projectId}/sprints/${props.sprint.id}`, opts);
    else form.post(`/projects/${props.projectId}/sprints`, opts);
};
</script>

<template>
    <Modal :show="show" :title="sprint ? 'Chỉnh sửa sprint' : 'Thêm sprint'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label">Tên sprint</label>
                <input v-model="form.name" type="text" class="input" placeholder="Sprint 1" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-danger">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="label">Mục tiêu</label>
                <textarea v-model="form.goal" rows="2" class="input resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Trạng thái</label>
                    <select v-model="form.status" class="input">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Thứ tự</label>
                    <input v-model.number="form.sort_order" type="number" min="0" class="input" />
                </div>
                <div>
                    <label class="label">Bắt đầu</label>
                    <input v-model="form.start_date" type="date" class="input" />
                </div>
                <div>
                    <label class="label">Kết thúc</label>
                    <input v-model="form.end_date" type="date" class="input" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="emit('close')">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ sprint ? 'Lưu' : 'Thêm' }}</button>
            </div>
        </form>
    </Modal>
</template>
