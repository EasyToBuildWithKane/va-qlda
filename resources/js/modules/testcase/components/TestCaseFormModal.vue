<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import AutocompleteInput from '@/shared/ui/form/AutocompleteInput.vue';
import { useToast } from '@/shared/composables/useToast';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { uploadFilesToTestCase, TEST_CASE_ATTACHMENTS_MAX_PENDING, TEST_CASE_ATTACHMENT_MAX_BYTES } from '@/composables/useTestCaseAttachmentUpload';

const props = defineProps({
    show: { type: Boolean, default: false },
    testCase: { type: Object, default: null },
    projectId: { type: Number, default: null },
    projectCode: { type: String, default: '' },
    projectName: { type: String, default: '' },
    projects: { type: Array, default: () => [] },
    testSuites: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const confirmDelete = useConfirmDelete();

const isEditing = computed(() => Boolean(props.testCase?.id));
const lockProject = computed(() => Boolean(props.projectId));
const canAttachFiles = computed(() => Boolean(props.testCase?.can?.update || !isEditing.value));

const form = useForm({
    project_id: null,
    suite_id: null,
    suite_name: '',
    title: '',
    preconditions: '',
    steps: [],
    expected_result: '',
    reference_links: [],
    priority: 'medium',
    status: 'draft',
    owner_id: null,
});

const pendingFiles = ref([]);
const fileInput = ref(null);
const uploadingFiles = ref(false);

const projectOptions = computed(() => (props.projects ?? []).map((p) => ({
    id: p.id ?? p.value,
    name: p.name ?? p.label,
    code: p.code ?? '',
})));

const employeeOptions = computed(() => (props.employees ?? []).map((e) => ({
    id: e.id ?? e.value,
    name: e.name ?? e.label,
})));

const suitesForProject = computed(() => {
    const pid = Number(form.project_id);
    if (!pid) return [];
    return (props.testSuites ?? []).filter((s) => Number(s.project_id) === pid);
});

const lockedProjectLabel = computed(() => {
    if (props.projectName && props.projectCode) return `${props.projectCode} · ${props.projectName}`;
    return props.projectName || props.projectCode || '';
});

const attachmentList = computed(() => props.testCase?.attachments ?? []);

const formDirty = computed(() => form.isDirty || pendingFiles.value.length > 0);

const referenceLinkError = computed(() => {
    const hit = Object.entries(form.errors).find(([key]) => key.startsWith('reference_links'));
    return hit ? hit[1] : null;
});

function revokePending(entry) {
    if (entry?.preview) URL.revokeObjectURL(entry.preview);
}

function clearPendingFiles() {
    pendingFiles.value.forEach(revokePending);
    pendingFiles.value = [];
}

onBeforeUnmount(clearPendingFiles);

function resetForm(tc) {
    form.project_id = tc?.project_id ?? props.projectId ?? null;
    form.suite_id = tc?.suite_id ?? tc?.suite?.id ?? null;
    form.suite_name = '';
    form.title = tc?.title ?? '';
    form.preconditions = tc?.preconditions ?? '';
    form.steps = tc?.steps ? JSON.parse(JSON.stringify(tc.steps)) : [];
    form.expected_result = tc?.expected_result ?? '';
    form.reference_links = (tc?.reference_links ?? []).map((l) => ({
        label: l?.label ?? '',
        url: l?.url ?? '',
    }));
    form.priority = tc?.priority?.value ?? 'medium';
    form.status = tc?.status?.value ?? 'draft';
    form.owner_id = tc?.owner_id ?? tc?.owner?.id ?? null;
    form.clearErrors();
    clearPendingFiles();
}

watch(() => props.show, (v) => {
    if (v) resetForm(props.testCase);
}, { immediate: true });

function onProjectChange(id) {
    const prev = form.project_id;
    form.project_id = id;
    if (String(prev ?? '') !== String(id ?? '')) {
        form.suite_id = null;
        form.suite_name = '';
    }
}

function onSuiteChange(id) {
    form.suite_id = id;
    if (id) form.suite_name = '';
}

function onSuiteCreate(name) {
    form.suite_id = null;
    form.suite_name = name || '';
}

function addStep() {
    form.steps.push({ step: '', expected: '' });
}

function removeStep(idx) {
    form.steps.splice(idx, 1);
}

function moveStep(idx, dir) {
    const to = idx + dir;
    if (to < 0 || to >= form.steps.length) return;
    const temp = form.steps[idx];
    form.steps[idx] = form.steps[to];
    form.steps[to] = temp;
}

function addLink() {
    if (form.reference_links.length >= 20) return;
    form.reference_links.push({ label: '', url: '' });
}

function removeLink(idx) {
    form.reference_links.splice(idx, 1);
}

function cleanedLinks() {
    return form.reference_links
        .map((l) => ({
            label: (l.label || '').trim(),
            url: (l.url || '').trim(),
        }))
        .filter((l) => l.url);
}

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i += 1;
    }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
}

function onPickFiles(e) {
    const files = [...(e.target.files || [])];
    if (fileInput.value) fileInput.value.value = '';
    if (!files.length || !canAttachFiles.value) return;

    const next = [...pendingFiles.value];
    let added = 0;
    for (const file of files) {
        if (file.size > TEST_CASE_ATTACHMENT_MAX_BYTES) {
            toast.warning(`«${file.name}» vượt quá 10MB.`);
            continue;
        }
        if (next.length >= TEST_CASE_ATTACHMENTS_MAX_PENDING) {
            if (added === 0) toast.warning('Tối đa 20 file chờ tải mỗi lần lưu.');
            break;
        }
        const isImage = (file.type || '').startsWith('image/');
        next.push({
            key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            file,
            name: file.name,
            size: file.size,
            isImage,
            preview: isImage ? URL.createObjectURL(file) : null,
        });
        added += 1;
    }
    pendingFiles.value = next;
}

function removePending(key) {
    const item = pendingFiles.value.find((p) => p.key === key);
    revokePending(item);
    pendingFiles.value = pendingFiles.value.filter((p) => p.key !== key);
}

function deleteSavedAttachment(att) {
    if (!props.testCase?.id || !att?.id) return;
    confirmDelete(`Xoá «${att.original_name}»?`, () => {
        router.delete(route('test-cases.attachments.destroy', {
            testCase: props.testCase.id,
            attachment: att.id,
        }), {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xoá file đính kèm.'),
            onError: () => toast.error('Không xoá được file.'),
        });
    }, { title: 'Xoá file đính kèm?' });
}

function finishSave(createdId = null) {
    const id = createdId || props.testCase?.id;
    const files = pendingFiles.value.map((p) => p.file).filter(Boolean);

    if (!id || !files.length) {
        clearPendingFiles();
        emit('saved');
        emit('close');
        return;
    }

    uploadingFiles.value = true;
    uploadFilesToTestCase(id, files, {
        onPartialError: () => toast.error('Một số file không tải lên được.'),
        onFinish: () => {
            uploadingFiles.value = false;
            clearPendingFiles();
            emit('saved');
            emit('close');
        },
    });
}

function submit() {
    const payload = {
        ...form.data(),
        reference_links: cleanedLinks(),
    };

    const routeName = isEditing.value
        ? route('test-cases.update', { testCase: props.testCase.id })
        : route('test-cases.store');
    const method = isEditing.value ? 'put' : 'post';

    form.transform(() => payload)[method](routeName, {
        preserveScroll: true,
        onSuccess: (page) => {
            toast.success(isEditing.value ? 'Đã cập nhật test case.' : 'Đã thêm test case.');
            const createdId = page?.props?.flash?.created_test_case_id ?? null;
            finishSave(createdId);
        },
        onError: () => toast.error('Có lỗi xảy ra. Vui lòng kiểm tra lại.'),
        onFinish: () => form.transform((data) => data),
    });
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEditing ? 'Sửa test case' : 'Thêm test case'"
    max-width="max-w-6xl"
    fit-viewport
    :dirty="formDirty"
    close-confirm-title="Huỷ thao tác?"
    close-confirm-message="Nội dung chưa lưu sẽ bị mất."
    @close="emit('close')"
  >
    <form
      class="flex min-h-0 flex-1 flex-col overflow-hidden"
      @submit.prevent="submit"
    >
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-0.5 [-webkit-overflow-scrolling:touch]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-5">
          <!-- Cột 1 — Meta -->
          <section class="space-y-3 lg:border-r lg:border-slate-100 lg:pr-4">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Phân loại
            </h3>

            <div v-if="lockProject">
              <p class="label mb-1">
                Dự án
              </p>
              <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <AppIcon
                  name="folder"
                  :size="14"
                  class="shrink-0 text-brand"
                />
                <span class="min-w-0 truncate font-medium text-slate-800">{{ lockedProjectLabel }}</span>
              </div>
            </div>
            <div v-else>
              <label
                class="label mb-1"
                for="tc-project"
              >Dự án <span class="text-rose-500">*</span></label>
              <AutocompleteInput
                id="tc-project"
                :model-value="form.project_id"
                :options="projectOptions"
                placeholder="Gõ tên hoặc mã dự án…"
                empty-text="Không tìm thấy dự án."
                :search-keys="['name', 'code']"
                subtitle-key="code"
                :panel-z-index="160"
                @update:model-value="onProjectChange"
              />
              <p
                v-if="form.errors.project_id"
                class="mt-1 text-xs text-rose-600"
              >
                {{ form.errors.project_id }}
              </p>
            </div>

            <div>
              <label
                class="label mb-1"
                for="tc-suite"
              >
                Nhóm kiểm thử
                <span class="font-normal text-slate-400">(tuỳ chọn)</span>
              </label>
              <AutocompleteInput
                id="tc-suite"
                :model-value="form.suite_id"
                :options="suitesForProject"
                :disabled="!form.project_id"
                :created-label="form.suite_name"
                placeholder="Vd: Đăng nhập…"
                empty-text="Gõ tên rồi chọn «Tạo nhóm»."
                creatable
                create-label="Tạo nhóm «{query}»"
                :panel-z-index="160"
                @update:model-value="onSuiteChange"
                @create="onSuiteCreate"
              />
              <p class="mt-1 text-[11px] text-slate-400">
                Gom case cùng tính năng/màn hình.
              </p>
            </div>

            <div>
              <label
                class="label mb-1"
                for="tc-priority"
              >Ưu tiên <span class="text-rose-500">*</span></label>
              <select
                id="tc-priority"
                v-model="form.priority"
                class="input w-full"
              >
                <option
                  v-for="opt in priorityOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div>
              <label
                class="label mb-1"
                for="tc-status"
              >Trạng thái</label>
              <select
                id="tc-status"
                v-model="form.status"
                class="input w-full"
              >
                <option
                  v-for="opt in statusOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div>
              <label
                class="label mb-1"
                for="tc-owner"
              >Người phụ trách</label>
              <AutocompleteInput
                id="tc-owner"
                :model-value="form.owner_id"
                :options="employeeOptions"
                placeholder="Gõ tên…"
                empty-text="Không tìm thấy."
                clearable
                :panel-z-index="160"
                @update:model-value="(v) => { form.owner_id = v; }"
              />
            </div>
          </section>

          <!-- Cột 2 — Nội dung -->
          <section class="space-y-3 lg:border-r lg:border-slate-100 lg:pr-4">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Nội dung
            </h3>

            <div>
              <label
                class="label mb-1"
                for="tc-title"
              >Tiêu đề <span class="text-rose-500">*</span></label>
              <input
                id="tc-title"
                v-model="form.title"
                type="text"
                class="input w-full"
                placeholder="Vd: Đăng nhập thành công với tài khoản hợp lệ"
                maxlength="255"
              >
              <p
                v-if="form.errors.title"
                class="mt-1 text-xs text-rose-600"
              >
                {{ form.errors.title }}
              </p>
            </div>

            <div>
              <label
                class="label mb-1"
                for="tc-preconditions"
              >Điều kiện tiên quyết</label>
              <textarea
                id="tc-preconditions"
                v-model="form.preconditions"
                class="input w-full resize-y"
                rows="4"
                placeholder="Cần có trước khi chạy…"
                maxlength="10000"
              />
            </div>

            <div>
              <label
                class="label mb-1"
                for="tc-expected"
              >Kết quả mong đợi</label>
              <textarea
                id="tc-expected"
                v-model="form.expected_result"
                class="input w-full resize-y"
                rows="4"
                placeholder="Kết quả đạt yêu cầu khi hoàn thành…"
                maxlength="10000"
              />
            </div>
          </section>

          <!-- Cột 3 — Bước + đính kèm -->
          <section class="space-y-3">
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                Bước & đính kèm
              </h3>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md bg-brand/10 px-2 py-1 text-xs font-medium text-brand hover:bg-brand/20"
                @click="addStep"
              >
                <AppIcon
                  name="plus"
                  :size="12"
                />
                Thêm bước
              </button>
            </div>

            <div
              v-if="!form.steps.length"
              class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-400"
            >
              Tuỳ chọn — thêm bước nếu cần mô tả từng thao tác.
            </div>

            <div class="max-h-48 space-y-2 overflow-y-auto pr-0.5">
              <div
                v-for="(step, idx) in form.steps"
                :key="idx"
                class="rounded-lg border border-slate-200 bg-slate-50/50 p-2"
              >
                <div class="mb-1.5 flex items-center justify-between gap-2">
                  <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand/10 text-[10px] font-bold text-brand">{{ idx + 1 }}</span>
                  <div class="flex items-center gap-0.5">
                    <button
                      type="button"
                      class="rounded p-0.5 text-slate-300 hover:text-slate-500"
                      :disabled="idx === 0"
                      @click="moveStep(idx, -1)"
                    >
                      <AppIcon
                        name="chevron-up"
                        :size="12"
                      />
                    </button>
                    <button
                      type="button"
                      class="rounded p-0.5 text-slate-300 hover:text-slate-500"
                      :disabled="idx === form.steps.length - 1"
                      @click="moveStep(idx, 1)"
                    >
                      <AppIcon
                        name="chevron-down"
                        :size="12"
                      />
                    </button>
                    <button
                      type="button"
                      class="rounded p-0.5 text-slate-300 hover:text-rose-500"
                      @click="removeStep(idx)"
                    >
                      <AppIcon
                        name="trash"
                        :size="12"
                      />
                    </button>
                  </div>
                </div>
                <textarea
                  v-model="step.step"
                  class="input mb-1.5 w-full resize-none"
                  rows="2"
                  placeholder="Thao tác…"
                  maxlength="2000"
                />
                <input
                  v-model="step.expected"
                  type="text"
                  class="input w-full"
                  placeholder="Kết quả bước…"
                  maxlength="2000"
                >
              </div>
            </div>

            <!-- Links -->
            <div class="border-t border-slate-100 pt-3">
              <div class="mb-1.5 flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Link tham chiếu</span>
                <button
                  type="button"
                  class="text-xs font-medium text-brand hover:underline"
                  :disabled="form.reference_links.length >= 20"
                  @click="addLink"
                >
                  + Thêm link
                </button>
              </div>
              <div
                v-if="!form.reference_links.length"
                class="rounded-lg border border-dashed border-slate-200 px-3 py-2 text-center text-[11px] text-slate-400"
              >
                Chèn URL tài liệu, Figma, ticket…
              </div>
              <div class="max-h-28 space-y-1.5 overflow-y-auto">
                <div
                  v-for="(link, index) in form.reference_links"
                  :key="index"
                  class="flex gap-1.5"
                >
                  <input
                    v-model="link.label"
                    type="text"
                    class="input w-20 shrink-0 text-sm"
                    placeholder="Nhãn"
                    maxlength="120"
                  >
                  <input
                    v-model="link.url"
                    type="url"
                    class="input min-w-0 flex-1 text-sm"
                    placeholder="https://…"
                    maxlength="2000"
                  >
                  <button
                    type="button"
                    class="shrink-0 px-1 text-xs text-rose-500 hover:underline"
                    @click="removeLink(index)"
                  >
                    Xoá
                  </button>
                </div>
              </div>
              <p
                v-if="referenceLinkError"
                class="mt-1 text-xs text-rose-600"
              >
                {{ referenceLinkError }}
              </p>
            </div>

            <!-- Files -->
            <div class="border-t border-slate-100 pt-3">
              <div class="mb-1.5 flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">File đính kèm</span>
                <button
                  v-if="canAttachFiles"
                  type="button"
                  class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                  @click="fileInput?.click()"
                >
                  <AppIcon
                    name="upload"
                    :size="12"
                  />
                  Chọn file
                </button>
              </div>
              <input
                ref="fileInput"
                type="file"
                class="hidden"
                multiple
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,image/*"
                @change="onPickFiles"
              >

              <ul
                v-if="attachmentList.length || pendingFiles.length"
                class="max-h-32 space-y-1 overflow-y-auto"
              >
                <li
                  v-for="att in attachmentList"
                  :key="`saved-${att.id}`"
                  class="flex items-center gap-2 rounded-md border border-slate-100 bg-white px-2 py-1.5 text-xs"
                >
                  <AppIcon
                    :name="att.is_image ? 'image' : 'documents'"
                    :size="13"
                    class="shrink-0 text-slate-400"
                  />
                  <a
                    v-if="att.url"
                    :href="att.url"
                    target="_blank"
                    rel="noopener"
                    class="min-w-0 flex-1 truncate text-slate-700 hover:text-brand"
                  >{{ att.original_name }}</a>
                  <span
                    v-else
                    class="min-w-0 flex-1 truncate text-slate-400"
                  >{{ att.original_name }}</span>
                  <button
                    v-if="canAttachFiles && isEditing"
                    type="button"
                    class="shrink-0 text-rose-500 hover:underline"
                    @click="deleteSavedAttachment(att)"
                  >
                    Xoá
                  </button>
                </li>
                <li
                  v-for="pf in pendingFiles"
                  :key="pf.key"
                  class="flex items-center gap-2 rounded-md border border-dashed border-brand/30 bg-brand/5 px-2 py-1.5 text-xs"
                >
                  <AppIcon
                    :name="pf.isImage ? 'image' : 'documents'"
                    :size="13"
                    class="shrink-0 text-brand"
                  />
                  <span class="min-w-0 flex-1 truncate text-slate-700">{{ pf.name }}</span>
                  <span class="shrink-0 text-[10px] text-slate-400">{{ formatSize(pf.size) }}</span>
                  <button
                    type="button"
                    class="shrink-0 text-rose-500 hover:underline"
                    @click="removePending(pf.key)"
                  >
                    Bỏ
                  </button>
                </li>
              </ul>
              <p
                v-else
                class="rounded-lg border border-dashed border-slate-200 px-3 py-2 text-center text-[11px] text-slate-400"
              >
                Ảnh, PDF, Excel… (tối đa 10MB/file)
              </p>
            </div>
          </section>
        </div>
      </div>

      <div class="mt-2.5 flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-2.5">
        <p
          v-if="pendingFiles.length"
          class="mr-auto text-xs text-slate-500"
        >
          {{ pendingFiles.length }} file sẽ tải lên sau khi lưu
        </p>
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
          :disabled="form.processing || uploadingFiles"
          @click="emit('close')"
        >
          Hủy
        </button>
        <button
          type="submit"
          class="btn-primary h-9 gap-1.5 px-4 text-sm"
          :disabled="form.processing || uploadingFiles"
        >
          <AppIcon
            name="save"
            :size="14"
          />
          {{ uploadingFiles ? 'Đang tải file…' : (form.processing ? 'Đang lưu…' : (isEditing ? 'Lưu thay đổi' : 'Thêm test case')) }}
        </button>
      </div>
    </form>
  </Modal>
</template>
