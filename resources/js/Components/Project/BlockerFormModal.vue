<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    severityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    defaultProjectId: { type: Number, default: null },
    lockProject: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    project_id: null, title: '', description: '', severity: 'medium', status: 'open',
    owner_id: null, resolution: '',
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.blocker) {
        form.project_id = props.blocker.project_id;
        form.title = props.blocker.title;
        form.description = props.blocker.description ?? '';
        form.severity = props.blocker.severity.value;
        form.status = props.blocker.status.value;
        form.owner_id = props.blocker.owner?.id ?? null;
        form.resolution = props.blocker.resolution ?? '';
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.blocker) form.put(`/blockers/${props.blocker.id}`, opts);
    else form.post('/blockers', opts);
};
</script>

<template>
    <Modal :show="show" :title="blocker ? 'Cập nhật vướng mắc' : 'Ghi nhận vướng mắc'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="!blocker">
                <label class="label">Dự án</label>
                <select v-model="form.project_id" class="input" :disabled="lockProject">
                    <option :value="null">— Chọn —</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <p v-if="form.errors.project_id" class="mt-1 text-xs text-danger">{{ form.errors.project_id }}</p>
            </div>

            <div>
                <label class="label">Tiêu đề</label>
                <input v-model="form.title" type="text" class="input" />
                <p v-if="form.errors.title" class="mt-1 text-xs text-danger">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="label">Mô tả</label>
                <textarea v-model="form.description" rows="3" class="input resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Mức độ</label>
                    <select v-model="form.severity" class="input">
                        <option v-for="o in severityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Trạng thái</label>
                    <select v-model="form.status" class="input">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="label">Người xử lý</label>
                    <select v-model="form.owner_id" class="input">
                        <option :value="null">— Chưa giao —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="blocker">
                <label class="label">Cách xử lý</label>
                <textarea v-model="form.resolution" rows="2" class="input resize-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="emit('close')">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ blocker ? 'Lưu' : 'Ghi nhận' }}</button>
            </div>
        </form>
    </Modal>
</template>
