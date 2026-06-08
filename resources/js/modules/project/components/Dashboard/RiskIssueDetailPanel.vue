<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import { datetime } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import { useConfirmDelete } from '@/composables/useConfirmClose';

const props = defineProps({
    row: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canUpload: { type: Boolean, default: false },
    canComment: { type: Boolean, default: false },
});

const toast = useToast();
const confirmDelete = useConfirmDelete();
const fileInput = ref(null);
const uploading = ref(false);

const normalizeList = (val) => {
    if (Array.isArray(val)) return val;
    if (val?.data && Array.isArray(val.data)) return val.data;
    return [];
};

const normalizeEvidenceLinks = (val) => {
    const list = normalizeList(val);
    return list.map((item) => ({
        label: (item?.label ?? '').trim(),
        url: (item?.url ?? '').trim(),
    })).filter((item) => item.url);
};

const comments = computed(() => normalizeList(props.row.comments));
const attachments = computed(() => normalizeList(props.row.attachments));
const activities = computed(() => normalizeList(props.row.activities));

const form = useForm({
    description: '',
    root_cause: '',
    resolution: '',
    evidence_links: [],
});

const syncForm = () => {
    form.description = props.row.description || '';
    form.root_cause = props.row.root_cause || '';
    form.resolution = props.row.resolution || '';
    form.evidence_links = normalizeEvidenceLinks(props.row.evidence_links).map((l) => ({ ...l }));
};

watch(() => props.row.id, syncForm, { immediate: true });

const evidenceLinksDirty = computed(() => {
    const current = normalizeEvidenceLinks(form.evidence_links);
    const original = normalizeEvidenceLinks(props.row.evidence_links);
    return JSON.stringify(current) !== JSON.stringify(original);
});

const dirty = computed(() =>
    form.description !== (props.row.description || '')
    || form.root_cause !== (props.row.root_cause || '')
    || form.resolution !== (props.row.resolution || '')
    || evidenceLinksDirty.value,
);

const saveDetails = () => {
    if (!dirty.value) return;
    const payload = {
        description: form.description,
        root_cause: form.root_cause,
        resolution: form.resolution,
        evidence_links: normalizeEvidenceLinks(form.evidence_links),
    };
    form.transform(() => payload).put(`/blockers/${props.row.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã lưu chi tiết'),
    });
};

const addEvidenceLink = () => {
    if (form.evidence_links.length >= 20) return;
    form.evidence_links.push({ label: '', url: '' });
};

const removeEvidenceLink = (index) => {
    form.evidence_links.splice(index, 1);
};

const pickFiles = () => fileInput.value?.click();

const onFilesSelected = (event) => {
    const files = [...(event.target.files || [])];
    if (!files.length) return;
    uploading.value = true;
    router.post(`/blockers/${props.row.id}/attachments`, { files }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Đã tải file lên'),
        onFinish: () => {
            uploading.value = false;
            event.target.value = '';
        },
    });
};

const removeAttachment = (file) => {
    confirmDelete(
        `Xoá file "${file.original_name}"?`,
        () => router.delete(`/blockers/${props.row.id}/attachments/${file.id}`, { preserveScroll: true }),
        { title: 'Xoá file đính kèm' },
    );
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
};

const linkLabel = (item) => item.label || item.url;

const activityIcon = (event) => {
    const map = {
        created: 'add',
        updated: 'edit',
        status_changed: 'check',
        comment: 'comment',
        attachment: 'template',
    };
    return map[event] || 'report-history';
};

const timeline = computed(() => {
    const items = [...activities.value];
    if (!items.some((a) => a.event === 'created')) {
        items.push({
            id: 'raised',
            event: 'created',
            description: 'Phát hiện rủi ro / vướng mắc',
            employee: props.row.raised_by,
            created_at: props.row.raised_at,
        });
    }
    return items.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

const displayEvidenceLinks = computed(() => normalizeEvidenceLinks(props.row.evidence_links));
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-2">
    <div class="space-y-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Mô tả chi tiết
        </p>
        <textarea
          v-if="canEdit"
          v-model="form.description"
          rows="3"
          class="input mt-1.5 w-full resize-y text-sm"
          placeholder="Mô tả vấn đề, bối cảnh, tác động…"
        />
        <p
          v-else
          class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300"
        >
          {{ row.description || '—' }}
        </p>
      </div>

      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Nguyên nhân
        </p>
        <textarea
          v-if="canEdit"
          v-model="form.root_cause"
          rows="2"
          class="input mt-1.5 w-full resize-y text-sm"
          placeholder="Nguyên nhân gốc rễ…"
        />
        <p
          v-else
          class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300"
        >
          {{ row.root_cause || '—' }}
        </p>
      </div>

      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Hướng xử lý
        </p>
        <textarea
          v-if="canEdit"
          v-model="form.resolution"
          rows="2"
          class="input mt-1.5 w-full resize-y text-sm"
          placeholder="Biện pháp, bước tiếp theo…"
        />
        <p
          v-else
          class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300"
        >
          {{ row.resolution || '—' }}
        </p>
      </div>

      <div>
        <div class="flex items-center justify-between gap-2">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Link dẫn chứng
          </p>
          <button
            v-if="canEdit"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand hover:bg-brand/10"
            :disabled="form.evidence_links.length >= 20"
            @click="addEvidenceLink"
          >
            <AppIcon
              name="add"
              :size="14"
            />
            Thêm link
          </button>
        </div>

        <div
          v-if="canEdit"
          class="mt-2 space-y-2"
        >
          <div
            v-for="(link, index) in form.evidence_links"
            :key="index"
            class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-2.5 sm:flex-row sm:items-start dark:border-slate-600 dark:bg-slate-900"
          >
            <input
              v-model="link.label"
              type="text"
              class="input text-sm sm:w-36"
              placeholder="Nhãn (tuỳ chọn)"
            >
            <input
              v-model="link.url"
              type="url"
              class="input min-w-0 flex-1 text-sm"
              placeholder="https://…"
            >
            <button
              type="button"
              class="shrink-0 self-end text-xs text-rose-500 hover:underline sm:self-center"
              @click="removeEvidenceLink(index)"
            >
              Xoá
            </button>
          </div>
          <p
            v-if="!form.evidence_links.length"
            class="rounded-xl border border-dashed border-slate-200 p-3 text-xs text-slate-400 dark:border-slate-600"
          >
            Chưa có link dẫn chứng (Jira, Figma, log, ticket…).
          </p>
        </div>
        <ul
          v-else-if="displayEvidenceLinks.length"
          class="mt-2 space-y-1.5"
        >
          <li
            v-for="(link, index) in displayEvidenceLinks"
            :key="index"
          >
            <a
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex max-w-full items-center gap-1.5 text-sm font-medium text-brand hover:underline"
            >
              <AppIcon
                name="dependency"
                :size="14"
                class="shrink-0"
              />
              <span class="truncate">{{ linkLabel(link) }}</span>
            </a>
          </li>
        </ul>
        <p
          v-else
          class="mt-2 text-xs text-slate-400"
        >
          Chưa có link dẫn chứng.
        </p>
      </div>

      <div
        v-if="canEdit && dirty"
        class="flex justify-end"
      >
        <button
          type="button"
          class="btn-primary text-xs"
          :disabled="form.processing"
          @click="saveDetails"
        >
          Lưu chi tiết
        </button>
      </div>

      <div>
        <div class="flex items-center justify-between gap-2">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Ảnh & file đính kèm
          </p>
          <button
            v-if="canUpload"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand hover:bg-brand/10"
            :disabled="uploading"
            @click="pickFiles"
          >
            <AppIcon
              name="add"
              :size="14"
            />
            {{ uploading ? 'Đang tải…' : 'Tải lên' }}
          </button>
          <input
            ref="fileInput"
            type="file"
            class="hidden"
            multiple
            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt"
            @change="onFilesSelected"
          >
        </div>

        <div
          v-if="attachments.length"
          class="mt-2 grid gap-2 sm:grid-cols-2"
        >
          <div
            v-for="file in attachments"
            :key="file.id"
            class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-900"
          >
            <template v-if="file.file_available !== false && file.url">
              <a
                v-if="file.is_image"
                :href="file.url"
                target="_blank"
                rel="noopener"
                class="block"
              >
                <img
                  :src="file.url"
                  :alt="file.original_name"
                  class="aspect-video w-full object-cover"
                >
              </a>
              <a
                v-else
                :href="file.url"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-2 p-3 text-sm text-slate-700 hover:text-brand dark:text-slate-200"
              >
                <AppIcon
                  name="template"
                  :size="18"
                  class="shrink-0 text-slate-400"
                />
                <span class="min-w-0 truncate font-medium">{{ file.original_name }}</span>
              </a>
            </template>
            <div
              v-else
              class="flex items-center gap-2 p-3 text-sm text-slate-400"
            >
              <AppIcon
                name="template"
                :size="18"
              />
              <span class="truncate">{{ file.original_name }} (file không còn trên máy chủ)</span>
            </div>
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-2.5 py-1.5 text-[10px] text-slate-400 dark:border-slate-700">
              <span>{{ formatSize(file.size) }}</span>
              <div class="flex items-center gap-2">
                <a
                  v-if="file.url"
                  :href="file.url"
                  class="inline-flex items-center gap-0.5 text-brand hover:underline"
                  :title="`Tải ${file.original_name}`"
                >
                  <AppIcon
                    name="download"
                    :size="12"
                  />
                  Tải về
                </a>
                <button
                  v-if="canUpload"
                  type="button"
                  class="text-rose-500 opacity-0 transition group-hover:opacity-100"
                  @click="removeAttachment(file)"
                >
                  Xoá
                </button>
              </div>
            </div>
          </div>
        </div>
        <p
          v-else
          class="mt-2 rounded-xl border border-dashed border-slate-200 p-3 text-xs text-slate-400 dark:border-slate-600"
        >
          Chưa có file đính kèm. Hỗ trợ hình ảnh, PDF, Office, ZIP (tối đa 10MB/file).
        </p>
      </div>
    </div>

    <div class="space-y-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
          Lịch sử cập nhật
        </p>
        <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto pr-1">
          <li
            v-for="item in timeline"
            :key="item.id"
            class="flex gap-2 text-xs text-slate-600 dark:text-slate-300"
          >
            <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700">
              <AppIcon
                :name="activityIcon(item.event)"
                :size="12"
              />
            </span>
            <div class="min-w-0 flex-1">
              <p class="font-medium text-slate-700 dark:text-slate-200">
                {{ item.description }}
              </p>
              <p class="text-slate-400">
                {{ datetime(item.created_at) }}
                <span v-if="item.employee?.name"> · {{ item.employee.name }}</span>
              </p>
            </div>
          </li>
        </ul>
        <p
          v-if="row.resolved_at"
          class="mt-2 text-xs text-emerald-600 dark:text-emerald-400"
        >
          Giải quyết: {{ datetime(row.resolved_at) }}
        </p>
      </div>

      <CommentThread
        :comments="comments"
        commentable-type="blocker"
        :commentable-id="row.id"
        :can-comment="canComment"
      />
    </div>
  </div>
</template>
