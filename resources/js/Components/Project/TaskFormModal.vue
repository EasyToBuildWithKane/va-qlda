<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    task: { type: Object, default: null },
    sprints: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] }, // for dependency picker
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    defaultStatus: { type: String, default: 'todo' },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    title: '', description: '', sprint_id: null, status: 'todo', priority: 'medium',
    assignee_id: null, start_date: null, due_date: null, estimate_hours: null,
    progress: 0, dependencies: [],
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.task) {
        form.title = props.task.title;
        form.description = props.task.description ?? '';
        form.sprint_id = props.task.sprint_id;
        form.status = props.task.status.value;
        form.priority = props.task.priority.value;
        form.assignee_id = props.task.assignee?.id ?? null;
        form.start_date = props.task.start_date;
        form.due_date = props.task.due_date;
        form.estimate_hours = props.task.estimate_hours;
        form.progress = props.task.progress;
        form.dependencies = [...(props.task.dependencies ?? [])];
    } else {
        form.reset();
        form.status = props.defaultStatus;
    }
});

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    };
    if (props.task) {
        form.put(`/projects/${props.projectId}/tasks/${props.task.id}`, opts);
    } else {
        form.post(`/projects/${props.projectId}/tasks`, opts);
    }
};

const dependencyOptions = () => props.tasks.filter((t) => !props.task || t.id !== props.task.id);
</script>

<template>
    <Modal :show="show" :title="task ? 'Chỉnh sửa công việc' : 'Thêm công việc'" max-width="max-w-2xl" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label">Tiêu đề</label>
                <input v-model="form.title" type="text" class="input" placeholder="Tên công việc" />
                <p v-if="form.errors.title" class="mt-1 text-xs text-danger">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="label">Mô tả</label>
                <textarea v-model="form.description" rows="3" class="input resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Trạng thái</label>
                    <select v-model="form.status" class="input">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Ưu tiên</label>
                    <select v-model="form.priority" class="input">
                        <option v-for="o in priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Sprint</label>
                    <select v-model="form.sprint_id" class="input">
                        <option :value="null">— Không —</option>
                        <option v-for="s in sprints" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Người phụ trách</label>
                    <select v-model="form.assignee_id" class="input">
                        <option :value="null">— Chưa giao —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Bắt đầu</label>
                    <input v-model="form.start_date" type="date" class="input" />
                </div>
                <div>
                    <label class="label">Kết thúc</label>
                    <input v-model="form.due_date" type="date" class="input" />
                    <p v-if="form.errors.due_date" class="mt-1 text-xs text-danger">{{ form.errors.due_date }}</p>
                </div>
                <div>
                    <label class="label">Ước lượng (giờ)</label>
                    <input v-model="form.estimate_hours" type="number" step="0.5" min="0" class="input" />
                </div>
                <div>
                    <label class="label">Tiến độ: {{ form.progress }}%</label>
                    <input v-model.number="form.progress" type="range" min="0" max="100" step="5" class="w-full" />
                </div>
            </div>

            <div v-if="dependencyOptions().length">
                <label class="label">Phụ thuộc vào</label>
                <div class="max-h-32 space-y-1 overflow-y-auto rounded-input border border-slate-200 p-2">
                    <label v-for="t in dependencyOptions()" :key="t.id" class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.dependencies" type="checkbox" :value="t.id" class="rounded" />
                        {{ t.title }}
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="emit('close')">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ task ? 'Lưu' : 'Thêm' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
