<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { useToast } from '@/shared/composables/useToast';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import {
    useRoutineTasks,
    todayIso,
    hoursBetween,
    hoursLabel,
    addMinutesToTime,
} from '@/modules/routine-task/composables/useRoutineTasks';

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const MAX_PENDING = 10;
const DURATION_PRESETS = [
    { label: '15p', mins: 15 },
    { label: '30p', mins: 30 },
    { label: '1h', mins: 60 },
    { label: '1h30', mins: 90 },
    { label: '2h', mins: 120 },
];

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const toast = useToast();
const confirmDelete = useConfirmDelete();
const { createTask, updateTask, deleteAttachment } = useRoutineTasks();

const fileInput = ref(null);
const pendingFiles = ref([]);
const submitting = ref(false);
const hydrating = ref(false);

const isEditing = computed(() => Boolean(props.task?.id));
const modalTitle = computed(() => {
    if (!props.canEdit) return 'Chi tiết công việc';
    return isEditing.value ? 'Cập nhật công việc' : 'Ghi nhận công việc';
});

const form = useForm({
    title: '',
    description: '',
    status: 'todo',
    work_date: todayIso(),
    start_time: '',
    end_time: '',
    estimate_hours: null,
    actual_hours: null,
    progress_percent: 0,
    blockers: '',
    risks: '',
});

const derivedHours = computed(() => hoursBetween(form.start_time, form.end_time));
const displayActual = computed(() => {
    if (form.actual_hours != null && form.actual_hours !== '') return Number(form.actual_hours);
    return derivedHours.value;
});
const etValue = computed(() => {
    const n = Number(form.estimate_hours);
    return Number.isFinite(n) && n > 0 ? n : null;
});
const performanceRatio = computed(() => {
    const et = etValue.value;
    const actual = displayActual.value;
    if (!et || actual == null) return null;
    return Math.round((actual / et) * 100);
});
const formDirty = computed(() => form.isDirty || pendingFiles.value.length > 0);
const savedAttachments = computed(() => props.task?.attachments ?? []);

watch(() => props.show, (open) => {
    if (!open) return;
    clearPending();
    hydrateFromTask();
});

function emptyForm() {
    return {
        title: '',
        description: '',
        status: 'todo',
        work_date: todayIso(),
        start_time: '',
        end_time: '',
        estimate_hours: null,
        actual_hours: null,
        progress_percent: 0,
        blockers: '',
        risks: '',
    };
}

function hydrateFromTask() {
    hydrating.value = true;
    const next = props.task?.id
        ? {
            title: props.task.title ?? '',
            description: props.task.description ?? '',
            status: props.task.status?.value ?? 'todo',
            work_date: props.task.work_date ?? '',
            start_time: props.task.start_time ?? '',
            end_time: props.task.end_time ?? '',
            estimate_hours: props.task.estimate_hours ?? null,
            actual_hours: props.task.actual_hours ?? null,
            progress_percent: Number(props.task.progress_percent ?? 0),
            blockers: props.task.blockers ?? '',
            risks: props.task.risks ?? '',
        }
        : emptyForm();
    form.defaults(next);
    form.reset();
    form.clearErrors();
    nextTick(() => { hydrating.value = false; });
}

watch(() => form.status, (status, prev) => {
    if (!props.show || hydrating.value || status === prev) return;
    if (status === 'done') form.progress_percent = 100;
    else if (status === 'todo' && form.progress_percent === 100) form.progress_percent = 0;
    else if (status === 'in_progress' && (form.progress_percent === 0 || form.progress_percent === 100)) {
        form.progress_percent = 50;
    }
});

function nowRounded() {
    const d = new Date();
    const mins = Math.round(d.getMinutes() / 5) * 5;
    d.setMinutes(mins, 0, 0);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function applyDuration(mins) {
    if (!form.start_time) form.start_time = nowRounded();
    form.end_time = addMinutesToTime(form.start_time, mins);
    if (form.estimate_hours == null || form.estimate_hours === '') {
        form.estimate_hours = Math.round((mins / 60) * 100) / 100;
    }
}

function fillActualFromRange() {
    if (derivedHours.value != null) form.actual_hours = derivedHours.value;
}

function onPickFiles(e) {
    const files = [...(e.target.files || [])];
    if (fileInput.value) fileInput.value.value = '';
    if (!files.length || !props.canEdit) return;
    const next = [...pendingFiles.value];
    for (const file of files) {
        if (file.size > MAX_FILE_BYTES) {
            toast.warning(`«${file.name}» vượt quá 10MB.`);
            continue;
        }
        if (next.length >= MAX_PENDING) {
            toast.warning('Tối đa 10 tệp mỗi lần lưu.');
            break;
        }
        next.push({
            key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            file,
            name: file.name,
            size: file.size,
            isImage: (file.type || '').startsWith('image/'),
        });
    }
    pendingFiles.value = next;
}

function clearPending() {
    pendingFiles.value = [];
}

function removePending(key) {
    pendingFiles.value = pendingFiles.value.filter((p) => p.key !== key);
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

function onDeleteSaved(att) {
    if (!props.task?.id || !att?.id) return;
    confirmDelete(`Xoá «${att.original_name}»?`, () => {
        deleteAttachment(props.task.id, att.id, {
            onSuccess: () => toast.success('Đã xoá tệp đính kèm.'),
            onError: () => toast.error('Không xoá được tệp.'),
        });
    }, { title: 'Xoá tệp đính kèm', confirmText: 'Xoá' });
}

function payload() {
    const files = pendingFiles.value.map((p) => p.file).filter(Boolean);
    return {
        title: form.title,
        description: form.description || null,
        status: form.status,
        work_date: form.work_date || null,
        start_time: form.start_time || null,
        end_time: form.end_time || null,
        estimate_hours: form.estimate_hours === '' ? null : form.estimate_hours,
        actual_hours: form.actual_hours === '' ? null : form.actual_hours,
        progress_percent: form.progress_percent,
        blockers: form.blockers || null,
        risks: form.risks || null,
        ...(files.length ? { files } : {}),
    };
}

function submit() {
    if (!props.canEdit || submitting.value) return;
    const title = form.title.trim();
    if (!title) {
        form.setError('title', 'Nhập tiêu đề công việc.');
        return;
    }
    submitting.value = true;
    const body = payload();
    const opts = {
        onSuccess: () => {
            clearPending();
            emit('close');
        },
        onError: (errors) => {
            Object.entries(errors || {}).forEach(([k, v]) => form.setError(k, Array.isArray(v) ? v[0] : v));
            toast.error('Kiểm tra lại thông tin trên form.');
        },
        onFinish: () => { submitting.value = false; },
    };
    if (isEditing.value) updateTask(props.task.id, body, opts);
    else createTask(body, opts);
}
</script>

<template>
  <Modal
    :show="show"
    :title="modalTitle"
    max-width="max-w-6xl"
    fit-viewport
    :dirty="canEdit && formDirty"
    close-confirm-title="Huỷ ghi nhận?"
    close-confirm-message="Nội dung chưa lưu sẽ bị mất."
    @close="emit('close')"
  >
    <form
      class="flex min-h-0 flex-1 flex-col overflow-hidden"
      @submit.prevent="submit"
    >
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-0.5 [-webkit-overflow-scrolling:touch]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
          <!-- Thời gian + hiệu suất -->
          <section class="space-y-3 lg:col-span-4 lg:border-r lg:border-slate-100 lg:pr-4">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Thời gian &amp; hiệu suất
            </h3>

            <div>
              <label
                class="label mb-1"
                for="rt-date"
              >Ngày làm việc</label>
              <input
                id="rt-date"
                v-model="form.work_date"
                type="date"
                class="input h-10 w-full text-sm"
                :disabled="!canEdit"
              >
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label
                  class="label mb-1"
                  for="rt-start"
                >Giờ bắt đầu</label>
                <input
                  id="rt-start"
                  v-model="form.start_time"
                  type="time"
                  class="input h-10 w-full text-sm tabular-nums"
                  :disabled="!canEdit"
                >
              </div>
              <div>
                <label
                  class="label mb-1"
                  for="rt-end"
                >Giờ kết thúc</label>
                <input
                  id="rt-end"
                  v-model="form.end_time"
                  type="time"
                  class="input h-10 w-full text-sm tabular-nums"
                  :disabled="!canEdit"
                >
              </div>
            </div>
            <p class="text-[11px] text-slate-400">
              Ví dụ họp 09:00–10:30 — điền giờ để tính giờ thực tế.
            </p>

            <div
              v-if="canEdit"
              class="flex flex-wrap gap-1.5"
            >
              <button
                v-for="p in DURATION_PRESETS"
                :key="p.mins"
                type="button"
                class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-brand/40 hover:text-brand"
                @click="applyDuration(p.mins)"
              >
                {{ p.label }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label
                  class="label mb-1"
                  for="rt-et"
                >Giờ ET</label>
                <input
                  id="rt-et"
                  v-model="form.estimate_hours"
                  type="number"
                  min="0"
                  max="999"
                  step="0.25"
                  class="input h-10 w-full text-sm tabular-nums"
                  placeholder="2"
                  :disabled="!canEdit"
                >
              </div>
              <div>
                <label
                  class="label mb-1"
                  for="rt-actual"
                >Giờ thực tế</label>
                <input
                  id="rt-actual"
                  v-model="form.actual_hours"
                  type="number"
                  min="0"
                  max="999"
                  step="0.25"
                  class="input h-10 w-full text-sm tabular-nums"
                  :placeholder="derivedHours != null ? String(derivedHours) : 'Tự tính'"
                  :disabled="!canEdit"
                >
              </div>
            </div>
            <button
              v-if="canEdit && derivedHours != null"
              type="button"
              class="text-[11px] font-medium text-brand hover:underline"
              @click="fillActualFromRange"
            >
              Dùng {{ hoursLabel(derivedHours) }} từ khung giờ
            </button>

            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5">
              <div class="mb-1.5 flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Hiệu suất</span>
                <span
                  class="text-[11px] font-semibold tabular-nums"
                  :class="performanceRatio == null
                    ? 'text-slate-400'
                    : performanceRatio <= 100 ? 'text-emerald-700' : 'text-amber-700'"
                >
                  {{ performanceRatio != null ? `${performanceRatio}% ET` : 'Chưa đủ giờ ET' }}
                </span>
              </div>
              <ProgressBar
                :value="performanceRatio == null ? 0 : Math.min(performanceRatio, 140)"
                :show-label="false"
                height="h-1.5"
              />
              <p class="mt-1.5 text-[11px] text-slate-500">
                ET {{ hoursLabel(etValue) || 'chưa nhập' }}
                · thực tế {{ hoursLabel(displayActual) || 'chưa ghi' }}
              </p>
            </div>
          </section>

          <!-- Nội dung -->
          <section class="space-y-3 lg:col-span-5 lg:border-r lg:border-slate-100 lg:pr-4">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Công việc
            </h3>

            <div>
              <label
                class="label mb-1"
                for="rt-title"
              >Tiêu đề <span class="text-rose-500">*</span></label>
              <input
                id="rt-title"
                v-model="form.title"
                type="text"
                class="input h-10 w-full text-sm"
                maxlength="255"
                placeholder="Vd: Họp standup đội sản phẩm"
                :disabled="!canEdit"
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
                for="rt-desc"
              >Mô tả / kết quả</label>
              <textarea
                id="rt-desc"
                v-model="form.description"
                rows="3"
                class="input w-full resize-y text-sm"
                maxlength="5000"
                placeholder="Ghi chú ngắn: đã chốt gì, việc còn lại…"
                :disabled="!canEdit"
              />
            </div>

            <div>
              <label
                class="label mb-1"
                for="rt-blockers"
              >Vướng mắc</label>
              <textarea
                id="rt-blockers"
                v-model="form.blockers"
                rows="2"
                class="input w-full resize-y text-sm"
                maxlength="5000"
                placeholder="Chờ phản hồi, thiếu tài nguyên…"
                :disabled="!canEdit"
              />
            </div>

            <div>
              <label
                class="label mb-1"
                for="rt-risks"
              >Rủi ro</label>
              <textarea
                id="rt-risks"
                v-model="form.risks"
                rows="2"
                class="input w-full resize-y text-sm"
                maxlength="5000"
                placeholder="Trễ deadline, phụ thuộc bên ngoài…"
                :disabled="!canEdit"
              />
            </div>
          </section>

          <!-- Tiến độ + file -->
          <section class="space-y-3 lg:col-span-3">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Tiến độ &amp; tệp
            </h3>

            <div>
              <label
                class="label mb-1"
                for="rt-status"
              >Trạng thái</label>
              <select
                id="rt-status"
                v-model="form.status"
                class="input h-10 w-full text-sm"
                :disabled="!canEdit"
              >
                <option
                  v-for="s in statuses"
                  :key="s.value"
                  :value="s.value"
                >
                  {{ s.label }}
                </option>
              </select>
            </div>

            <div>
              <div class="mb-1 flex items-center justify-between">
                <label
                  class="label mb-0"
                  for="rt-progress"
                >Tiến độ</label>
                <span class="text-xs font-semibold tabular-nums text-slate-600">{{ form.progress_percent }}%</span>
              </div>
              <input
                id="rt-progress"
                v-model.number="form.progress_percent"
                type="range"
                min="0"
                max="100"
                step="5"
                class="h-2 w-full cursor-pointer accent-brand"
                :disabled="!canEdit"
              >
              <ProgressBar
                :value="form.progress_percent"
                :show-label="false"
                height="h-1.5"
                class="mt-1.5"
              />
            </div>

            <div class="border-t border-slate-100 pt-3">
              <div class="mb-1.5 flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Tệp đính kèm</span>
                <button
                  v-if="canEdit"
                  type="button"
                  class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                  @click="fileInput?.click()"
                >
                  <AppIcon
                    name="upload"
                    :size="12"
                  />
                  Chọn tệp
                </button>
              </div>
              <input
                ref="fileInput"
                type="file"
                class="hidden"
                multiple
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,image/*"
                @change="onPickFiles"
              >

              <ul
                v-if="savedAttachments.length || pendingFiles.length"
                class="max-h-36 space-y-1 overflow-y-auto"
              >
                <li
                  v-for="att in savedAttachments"
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
                    v-if="canEdit && isEditing"
                    type="button"
                    class="shrink-0 text-rose-500 hover:underline"
                    @click="onDeleteSaved(att)"
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
                    name="documents"
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
                Ảnh, PDF, Excel… tối đa 10MB/tệp
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
          {{ pendingFiles.length }} tệp sẽ tải lên khi lưu
        </p>
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-sm"
          @click="emit('close')"
        >
          {{ canEdit ? 'Huỷ' : 'Đóng' }}
        </button>
        <button
          v-if="canEdit"
          type="submit"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          :disabled="submitting || !form.title.trim()"
        >
          <AppIcon
            name="save"
            :size="15"
          />
          {{ isEditing ? 'Lưu thay đổi' : 'Ghi nhận' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
