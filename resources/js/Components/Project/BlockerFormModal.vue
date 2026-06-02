<script setup>
import { computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
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
    projectName: { type: String, default: '' },
    projectCode: { type: String, default: '' },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const form = useForm({
    project_id: null,
    title: '',
    description: '',
    root_cause: '',
    severity: 'medium',
    status: 'open',
    owner_id: null,
    due_date: null,
    resolution: '',
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.blocker) {
        form.project_id = props.blocker.project_id;
        form.title = props.blocker.title;
        form.description = props.blocker.description ?? '';
        form.root_cause = props.blocker.root_cause ?? '';
        form.severity = props.blocker.severity.value;
        form.status = props.blocker.status.value;
        form.owner_id = props.blocker.owner?.id ?? null;
        form.due_date = props.blocker.due_date ?? null;
        form.resolution = props.blocker.resolution ?? '';
    } else {
        form.reset();
        form.project_id = props.defaultProjectId;
        form.severity = 'medium';
        form.status = 'open';
    }
});

const activeProjectId = computed(() =>
    form.project_id ?? props.blocker?.project_id ?? props.defaultProjectId ?? null,
);

const projectDisplay = computed(() => {
    const embedded = props.blocker?.project;
    if (embedded?.name) {
        return embedded.code ? `${embedded.name} (${embedded.code})` : embedded.name;
    }
    const id = activeProjectId.value;
    if (id) {
        const p = props.projects.find((x) => x.id === id);
        if (p?.name) {
            return p.code ? `${p.name} (${p.code})` : p.name;
        }
    }
    if (props.projectName) {
        return props.projectCode ? `${props.projectName} (${props.projectCode})` : props.projectName;
    }
    return null;
});

const showProjectBanner = computed(() => props.lockProject || !!projectDisplay.value);

const modalTitle = computed(() => (props.blocker ? 'Cập nhật vướng mắc' : 'Ghi nhận vướng mắc'));

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.blocker) form.put(`/blockers/${props.blocker.id}`, opts);
    else form.post('/blockers', opts);
};
</script>

<template>
    <Modal
        :show="show"
        :dirty="form.isDirty"
        :title="modalTitle"
        max-width="max-w-4xl"
        @close="emit('close')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <!-- Dự án (tự động khi ghi trong ngữ cảnh dự án) -->
            <div
                v-if="showProjectBanner"
                class="flex items-start gap-3 rounded-xl border border-brand/25 bg-gradient-to-r from-brand/8 to-transparent px-4 py-3.5 dark:border-brand/30 dark:from-brand/15"
            >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-brand shadow-sm dark:bg-slate-800">
                    <AppIcon name="projects" :size="20" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Vướng mắc của dự án</p>
                    <p class="mt-0.5 truncate font-display text-base font-semibold text-slate-800 dark:text-slate-100">
                        {{ projectDisplay || '—' }}
                    </p>
                    <p v-if="lockProject" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Dự án được chọn tự động theo trang hiện tại
                    </p>
                </div>
            </div>

            <div v-else-if="!blocker" class="max-w-md">
                <label class="label">Dự án</label>
                <select v-model="form.project_id" class="input">
                    <option :value="null">— Chọn dự án —</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <p v-if="form.errors.project_id" class="mt-1 text-xs text-danger">{{ form.errors.project_id }}</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Cột trái: nội dung -->
                <div class="space-y-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nội dung vướng mắc</p>

                    <div>
                        <label class="label">Tiêu đề <span class="text-danger">*</span></label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="input"
                            placeholder="Tóm tắt ngắn gọn vướng mắc…"
                        />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-danger">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="label">Mô tả chi tiết</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            class="input resize-y"
                            placeholder="Bối cảnh, tác động, phạm vi ảnh hưởng…"
                        />
                    </div>

                    <div>
                        <label class="label">Nguyên nhân</label>
                        <textarea
                            v-model="form.root_cause"
                            rows="3"
                            class="input resize-y"
                            placeholder="Nguyên nhân gốc rễ (nếu đã xác định)…"
                        />
                    </div>
                </div>

                <!-- Cột phải: phân loại & xử lý -->
                <div class="space-y-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Phân loại & phân công</p>

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
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Hạn xử lý</label>
                            <input v-model="form.due_date" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">Người phụ trách</label>
                            <select v-model="form.owner_id" class="input">
                                <option :value="null">— Chưa giao —</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label">Hướng xử lý</label>
                        <textarea
                            v-model="form.resolution"
                            rows="4"
                            class="input resize-y"
                            placeholder="Kế hoạch xử lý, bước tiếp theo, người liên quan…"
                        />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="button" class="btn-ghost" @click="modalClose()">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ blocker ? 'Lưu thay đổi' : 'Ghi nhận vướng mắc' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
