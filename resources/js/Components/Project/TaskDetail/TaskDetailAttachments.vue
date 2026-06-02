<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { datetime } from '@/composables/useFormat';
import { useToast } from '@/composables/useToast';
import { normalizeEntities } from '@/composables/useNormalizeList';

const props = defineProps({
    taskId: { type: Number, required: true },
    projectId: { type: Number, required: true },
    attachments: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['uploaded']);

const fileList = computed(() => normalizeEntities(props.attachments));

const toast = useToast();
const dragging = ref(false);
const uploading = ref(false);
const fileInput = ref(null);

const onFiles = (fileList) => {
    if (!fileList?.length || !props.canEdit) return;
    uploading.value = true;
    const fd = new FormData();
    [...fileList].forEach((f) => fd.append('files[]', f));

    router.post(`/projects/${props.projectId}/tasks/${props.taskId}/attachments`, fd, {
        preserveScroll: true,
        only: ['tasks'],
        forceFormData: true,
        onSuccess: () => {
            toast.success('Đã tải file lên');
            emit('uploaded');
        },
        onError: () => toast.error('Không tải được file'),
        onFinish: () => { uploading.value = false; },
    });
};

const onDrop = (e) => {
    dragging.value = false;
    onFiles(e.dataTransfer?.files);
};

const removeFile = (att) => {
    if (!confirm(`Xoá "${att.original_name}"?`)) return;
    router.delete(`/projects/${props.projectId}/tasks/${props.taskId}/attachments/${att.id}`, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => toast.success('Đã xoá file'),
    });
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <section class="rounded-xl border border-slate-200/80 dark:border-slate-700">
        <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 dark:border-slate-800">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Đính kèm task</h3>
            <button
                v-if="canEdit"
                type="button"
                class="text-xs font-medium text-brand hover:underline"
                :disabled="uploading"
                @click="fileInput?.click()"
            >
                {{ uploading ? 'Đang tải…' : '+ Tải lên' }}
            </button>
            <input ref="fileInput" type="file" multiple class="hidden" @change="onFiles($event.target.files); $event.target.value = ''" />
        </div>

        <div
            v-if="canEdit"
            class="m-3 rounded-lg border-2 border-dashed px-4 py-6 text-center transition"
            :class="dragging ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-600'"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
        >
            <AppIcon name="upload" :size="24" class="mx-auto text-slate-400" />
            <p class="mt-2 text-xs text-slate-500">Kéo thả PDF, Office, ZIP, hình ảnh, video (tối đa 10MB/file)</p>
        </div>

        <ul v-if="fileList.length" class="divide-y divide-slate-100 dark:divide-slate-800">
            <li v-for="f in fileList" :key="f.id" class="flex items-center gap-3 px-3 py-2.5">
                <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 dark:bg-slate-800">
                    <AppIcon :name="f.is_image ? 'image' : 'documents'" :size="16" class="text-slate-500" />
                </div>
                <div class="min-w-0 flex-1">
                    <a :href="f.url" target="_blank" rel="noopener" class="truncate text-sm font-medium text-slate-800 hover:text-brand dark:text-slate-100">
                        {{ f.original_name }}
                    </a>
                    <p class="text-[10px] text-slate-400">
                        v{{ f.version }} · {{ formatSize(f.size) }} · {{ f.uploaded_by?.name || '—' }} · {{ datetime(f.created_at) }}
                    </p>
                </div>
                <a :href="f.url" target="_blank" rel="noopener" class="shrink-0 text-slate-400 hover:text-brand">
                    <AppIcon name="download" :size="16" />
                </a>
                <button
                    v-if="canEdit"
                    type="button"
                    class="shrink-0 text-slate-400 hover:text-rose-500"
                    @click="removeFile(f)"
                >
                    <AppIcon name="delete" :size="16" />
                </button>
            </li>
        </ul>
        <p v-else-if="!canEdit" class="px-3 py-6 text-center text-xs text-slate-400">Chưa có file đính kèm.</p>
    </section>
</template>
