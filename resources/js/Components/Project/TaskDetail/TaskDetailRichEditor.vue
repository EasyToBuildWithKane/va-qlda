<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    taskId: { type: Number, required: true },
    projectId: { type: Number, required: true },
    modelValue: { type: String, default: '' },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['saved']);

const toast = useToast();
const body = ref(props.modelValue || '');
const editing = ref(false);
const saving = ref(false);

watch(() => props.modelValue, (v) => { if (!editing.value) body.value = v || ''; });

const TOOLBAR = [
    { label: 'B', action: () => wrap('**'), title: 'Đậm' },
    { label: 'I', action: () => wrap('_'), title: 'Nghiêng' },
    { label: '•', action: () => insert('\n- '), title: 'Danh sách' },
    { label: '☐', action: () => insert('\n- [ ] '), title: 'Checklist' },
    { label: '@', action: () => insert('@'), title: 'Nhắc người (sắp có)' },
];

const wrap = (marker) => {
    const el = document.activeElement;
    if (el?.tagName !== 'TEXTAREA') return;
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const sel = body.value.slice(start, end) || 'văn bản';
    body.value = body.value.slice(0, start) + marker + sel + marker + body.value.slice(end);
};

const insert = (text) => {
    body.value += text;
};

const save = () => {
    saving.value = true;
    router.patch(`/projects/${props.projectId}/tasks/${props.taskId}`, { description: body.value }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => {
            editing.value = false;
            toast.success('Đã lưu mô tả');
            emit('saved');
        },
        onError: () => toast.error('Không lưu được mô tả'),
        onFinish: () => { saving.value = false; },
    });
};
</script>

<template>
    <section>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Mô tả</h3>
            <button
                v-if="canEdit && !editing"
                type="button"
                class="text-xs font-medium text-brand hover:underline"
                @click="editing = true"
            >
                Chỉnh sửa
            </button>
        </div>

        <div v-if="editing" class="overflow-hidden rounded-xl border border-brand/30 bg-white dark:bg-slate-900">
            <div class="flex flex-wrap gap-0.5 border-b border-slate-100 bg-slate-50 px-2 py-1 dark:border-slate-800 dark:bg-slate-800/50">
                <button
                    v-for="(t, i) in TOOLBAR"
                    :key="i"
                    type="button"
                    class="grid h-7 min-w-7 place-items-center rounded px-1.5 text-xs font-semibold text-slate-600 hover:bg-white dark:text-slate-300"
                    :title="t.title"
                    @click="t.action"
                >
                    {{ t.label }}
                </button>
            </div>
            <textarea v-model="body" rows="8" class="w-full resize-y border-0 bg-transparent px-3 py-2 text-sm focus:ring-0" placeholder="Markdown, checklist, liên kết…" />
            <div class="flex justify-end gap-2 border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                <button type="button" class="btn-ghost text-xs" @click="editing = false; body = props.modelValue || ''">Huỷ</button>
                <button type="button" class="btn-primary text-xs" :disabled="saving" @click="save">
                    <AppIcon name="save" :size="14" /> Lưu
                </button>
            </div>
        </div>
        <slot v-else />
    </section>
</template>
