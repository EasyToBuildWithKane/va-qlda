<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    feedback: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const reporterType = ref('external');
const form = useForm({
    project_id: null, category: 'improvement', title: '', description: '', rating: null,
    priority: 'medium', status: 'new',
    reporter_employee_id: null, reporter_name: '', reporter_email: '', assignee_id: null,
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.feedback) {
        form.project_id = props.feedback.project_id;
        form.category = props.feedback.category.value;
        form.title = props.feedback.title;
        form.description = props.feedback.description;
        form.rating = props.feedback.rating;
        form.priority = props.feedback.priority.value;
        form.status = props.feedback.status.value;
        form.assignee_id = props.feedback.assignee?.id ?? null;
    } else {
        form.reset();
        reporterType.value = 'external';
    }
});

watch(reporterType, (t) => {
    if (t === 'internal') { form.reporter_name = ''; form.reporter_email = ''; }
    else { form.reporter_employee_id = null; }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.feedback) form.put(`/feedback/${props.feedback.id}`, opts);
    else form.post('/feedback', opts);
};
</script>

<template>
    <Modal :show="show" :title="feedback ? `Chỉnh sửa ${feedback.code}` : 'Phản hồi mới'" max-width="max-w-2xl" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Dự án (tuỳ chọn)</label>
                    <select v-model="form.project_id" class="input">
                        <option :value="null">— Không gắn —</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Người xử lý</label>
                    <select v-model="form.assignee_id" class="input">
                        <option :value="null">— Chưa giao —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="label">Tiêu đề</label>
                <input v-model="form.title" type="text" class="input" />
                <p v-if="form.errors.title" class="mt-1 text-xs text-danger">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="label">Nội dung</label>
                <textarea v-model="form.description" rows="3" class="input resize-none"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-xs text-danger">{{ form.errors.description }}</p>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="label">Phân loại</label>
                    <select v-model="form.category" class="input">
                        <option v-for="o in categoryOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Ưu tiên</label>
                    <select v-model="form.priority" class="input">
                        <option v-for="o in priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Trạng thái</label>
                    <select v-model="form.status" class="input">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="label">Đánh giá (1–5)</label>
                <select v-model.number="form.rating" class="input w-32">
                    <option :value="null">—</option>
                    <option v-for="n in 5" :key="n" :value="n">{{ n }} ★</option>
                </select>
            </div>

            <fieldset v-if="!feedback" class="rounded-card border border-slate-200 p-3">
                <legend class="px-1 text-xs font-semibold text-slate-500">Người gửi phản hồi</legend>
                <div class="mb-2 flex gap-4 text-sm">
                    <label class="flex items-center gap-1.5"><input v-model="reporterType" type="radio" value="internal" /> Nội bộ</label>
                    <label class="flex items-center gap-1.5"><input v-model="reporterType" type="radio" value="external" /> Người dùng</label>
                </div>
                <select v-if="reporterType === 'internal'" v-model="form.reporter_employee_id" class="input">
                    <option :value="null">— Chọn nhân sự —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <div v-else class="grid grid-cols-2 gap-3">
                    <input v-model="form.reporter_name" type="text" class="input" placeholder="Họ tên" />
                    <input v-model="form.reporter_email" type="email" class="input" placeholder="Email" />
                </div>
                <p v-if="form.errors.reporter_name" class="mt-1 text-xs text-danger">{{ form.errors.reporter_name }}</p>
            </fieldset>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="emit('close')">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ feedback ? 'Lưu' : 'Gửi phản hồi' }}</button>
            </div>
        </form>
    </Modal>
</template>
