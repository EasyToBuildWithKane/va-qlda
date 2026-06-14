<script setup>
/* eslint-disable vue/no-v-html */
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DecimalHoursInput from '@/shared/ui/DecimalHoursInput.vue';
import DateInput from '@/shared/ui/form/DateInput.vue';
import TimeInput from '@/shared/ui/form/TimeInput.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import CoachingMaterialEmbed from '@/modules/coaching/components/CoachingMaterialEmbed.vue';
import CoachingSessionAssignmentsTab from '@/modules/coaching/components/CoachingSessionAssignmentsTab.vue';
import { useToast } from '@/shared/composables/useToast';
import { useDialog } from '@/composables/useDialog';
import { date as fmtDate, hours as fmtHours, timeOfDay } from '@/composables/useFormat';

function toTimeInputValue(value) {
    if (value == null || value === '') return '';
    const m = String(value).trim().match(/^(\d{1,2}):(\d{2})/);
    return m ? `${m[1].padStart(2, '0')}:${m[2]}` : '';
}

const props = defineProps({
    session: { type: Object, required: true },
    course: { type: Object, required: true },
    materialTypes: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();
const dialog = useDialog();

const editMode = ref(false);
const canEdit = computed(() => props.can?.update === true);
const isEditing = computed(() => editMode.value && canEdit.value);

const STATUS_LABEL = {
    pending: 'Chưa học', in_progress: 'Đang học', completed: 'Hoàn thành', cancelled: 'Hủy',
};
const STATUS_COLOR = {
    pending: 'slate', in_progress: 'amber', completed: 'emerald', cancelled: 'rose',
};
const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'overview' },
    { key: 'content', label: 'Nội dung', icon: 'documents' },
    { key: 'materials', label: 'Tài liệu', icon: 'link' },
    { key: 'assignments', label: 'Bài tập', icon: 'task' },
];

const activeTab = ref('overview');

const statusValue = computed(() => props.session.status?.value ?? 'pending');
const materialCount = computed(() => props.session.materials?.length ?? 0);
const assignmentCount = computed(() => props.session.assignments?.length ?? 0);
const hasContent = computed(() => Boolean(props.session.content));

const sessionTimeLabel = computed(() => {
    const s = props.session.start_time;
    const e = props.session.end_time;
    if (s && e) return `${timeOfDay(s)} – ${timeOfDay(e)}`;
    if (s) return `Bắt đầu ${timeOfDay(s)}`;
    if (e) return `Kết thúc ${timeOfDay(e)}`;
    return null;
});

const metaForm = useForm({
    title: props.session.title ?? '',
    date: props.session.date ?? '',
    total_hours: props.session.total_hours ?? null,
    start_time: toTimeInputValue(props.session.start_time),
    end_time: toTimeInputValue(props.session.end_time),
});

const materialForm = useForm({ type: 'youtube', title: '', url: '', file: null });
const contentForm = useForm({ content: props.session.content ?? '' });

const formsDirty = computed(
    () => metaForm.isDirty || contentForm.isDirty || materialForm.isDirty,
);

function sessionMetaDefaults() {
    return {
        title: props.session.title ?? '',
        date: props.session.date ?? '',
        total_hours: props.session.total_hours ?? null,
        start_time: toTimeInputValue(props.session.start_time),
        end_time: toTimeInputValue(props.session.end_time),
    };
}

function syncFormsFromSession() {
    metaForm.defaults(sessionMetaDefaults()).reset();
    contentForm.defaults({ content: props.session.content ?? '' }).reset();
    materialForm.reset();
}

function startEdit() {
    syncFormsFromSession();
    editMode.value = true;
}

async function cancelEdit() {
    if (formsDirty.value) {
        const ok = await dialog.confirm({
            title: 'Huỷ chỉnh sửa?',
            message: 'Thay đổi chưa lưu sẽ bị bỏ.',
            confirmText: 'Huỷ chỉnh sửa',
            cancelText: 'Tiếp tục sửa',
        });
        if (!ok) return;
    }
    syncFormsFromSession();
    editMode.value = false;
}

function leaveEditAfterSave() {
    syncFormsFromSession();
    editMode.value = false;
}

watch(
    () => props.session,
    () => {
        if (!editMode.value) syncFormsFromSession();
    },
    { deep: true },
);

function saveMeta() {
    metaForm.patch(`/coaching/sessions/${props.session.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã lưu thông tin buổi học.');
            leaveEditAfterSave();
        },
    });
}

function saveContent() {
    contentForm.patch(`/coaching/sessions/${props.session.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã lưu nội dung.');
            leaveEditAfterSave();
        },
    });
}

function submitMaterial() {
    materialForm.post(`/coaching/sessions/${props.session.id}/materials`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            materialForm.reset();
            toast.success('Đã thêm tài liệu.');
        },
    });
}

function tabBadge(key) {
    if (key === 'materials') return materialCount.value;
    if (key === 'assignments') return assignmentCount.value;
    return 0;
}
</script>

<template>
  <Head :title="`Buổi ${session.session_number}: ${session.title}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="`Buổi ${session.session_number}: ${session.title}`"
        :subtitle="course.name"
        icon="weekly"
        :back-href="`/coaching/courses/${course.id}`"
      >
        <Badge
          :label="STATUS_LABEL[statusValue] || statusValue"
          :color="STATUS_COLOR[statusValue] || 'slate'"
        />
        <button
          v-if="canEdit && !isEditing"
          type="button"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          @click="startEdit"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
          Chỉnh sửa
        </button>
        <button
          v-else-if="isEditing"
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-sm"
          @click="cancelEdit"
        >
          <AppIcon
            name="close"
            :size="15"
          />
          Xem chi tiết
        </button>
      </PageHeader>
    </template>

    <CoachingWorkspace>
      <div class="mx-auto w-full max-w-5xl space-y-4">
        <!-- Tóm tắt nhanh -->
        <section
          class="card overflow-hidden"
          aria-label="Tóm tắt buổi học"
        >
          <div class="border-b border-slate-100 bg-gradient-to-r from-brand-50/80 to-white px-4 py-3 sm:px-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="font-display text-sm font-semibold text-slate-800">
                {{ session.title || 'Chưa có tên buổi' }}
              </p>
              <Badge
                class="sm:hidden"
                :label="STATUS_LABEL[statusValue] || statusValue"
                :color="STATUS_COLOR[statusValue] || 'slate'"
              />
            </div>
          </div>
          <dl class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4 sm:gap-4 sm:p-5">
            <div class="rounded-lg bg-slate-50/90 px-3 py-2.5">
              <dt class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                <AppIcon
                  name="calendar"
                  :size="12"
                />
                Ngày học
              </dt>
              <dd class="mt-1 text-sm font-medium text-slate-800">
                {{ session.date ? fmtDate(session.date) : '—' }}
              </dd>
            </div>
            <div class="rounded-lg bg-slate-50/90 px-3 py-2.5">
              <dt class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                <AppIcon
                  name="worklog"
                  :size="12"
                />
                Tổng giờ
              </dt>
              <dd class="mt-1 text-sm font-medium text-slate-800">
                {{ session.total_hours != null ? fmtHours(session.total_hours) : '—' }}
              </dd>
            </div>
            <div class="rounded-lg bg-slate-50/90 px-3 py-2.5">
              <dt class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                <AppIcon
                  name="clock"
                  :size="12"
                />
                Khung giờ
              </dt>
              <dd class="mt-1 text-sm font-medium text-slate-800">
                {{ sessionTimeLabel || '—' }}
              </dd>
            </div>
            <div class="rounded-lg bg-slate-50/90 px-3 py-2.5">
              <dt class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                <AppIcon
                  name="link"
                  :size="12"
                />
                Tài liệu · Bài tập
              </dt>
              <dd class="mt-1 text-sm font-medium text-slate-800">
                {{ materialCount }} · {{ assignmentCount }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Tab shell -->
        <section class="card overflow-hidden">
          <nav
            class="flex shrink-0 items-stretch gap-0 overflow-x-auto overscroll-x-contain border-b border-slate-200 bg-white [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            aria-label="Nội dung buổi học"
          >
            <button
              v-for="t in tabs"
              :key="t.key"
              type="button"
              class="flex min-w-[5.5rem] shrink-0 flex-col items-center justify-center gap-0.5 border-b-2 px-3 py-3 text-center transition sm:min-w-0 sm:flex-row sm:gap-1.5 sm:px-4 sm:py-3.5 sm:text-left"
              :class="activeTab === t.key
                ? 'border-brand bg-brand-50/30 text-brand'
                : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
              @click="activeTab = t.key"
            >
              <AppIcon
                :name="t.icon"
                :size="16"
                class="shrink-0 opacity-80"
              />
              <span class="text-xs font-semibold sm:text-sm">{{ t.label }}</span>
              <span
                v-if="tabBadge(t.key) > 0"
                class="inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold"
                :class="activeTab === t.key ? 'bg-brand/15 text-brand' : 'bg-slate-200 text-slate-600'"
              >
                {{ tabBadge(t.key) }}
              </span>
            </button>
          </nav>

          <div
            v-if="isEditing"
            class="flex flex-wrap items-center justify-between gap-2 border-b border-brand/15 bg-brand-50/40 px-4 py-2.5 text-sm sm:px-6"
          >
            <span class="font-medium text-brand">
              Đang chỉnh sửa buổi học
            </span>
            <span class="text-xs text-slate-600">
              Lưu từng tab (Tổng quan / Nội dung) hoặc thêm tài liệu khi chỉnh sửa; bài tập giao tại tab Bài tập.
            </span>
          </div>

          <div class="p-4 sm:p-6">
            <!-- Tổng quan -->
            <div
              v-show="activeTab === 'overview'"
              class="mx-auto max-w-3xl"
            >
              <p
                v-if="isEditing"
                class="mb-4 text-sm text-slate-600"
              >
                Cập nhật tên buổi, ngày và khung giờ. Bấm «Lưu thông tin» rồi quay lại chế độ xem chi tiết.
              </p>
              <p
                v-else
                class="mb-4 text-sm text-slate-600"
              >
                Thông tin đã lưu của buổi học. Bấm «Chỉnh sửa» trên header để thay đổi.
              </p>
              <div
                v-if="isEditing"
                class="grid gap-4 sm:grid-cols-2"
              >
                <div class="sm:col-span-2">
                  <label class="label">Tên buổi học</label>
                  <input
                    v-model="metaForm.title"
                    type="text"
                    class="input w-full"
                    placeholder="Tên hiển thị trên lịch và chi tiết buổi"
                  >
                </div>
                <div>
                  <label class="label">Ngày học</label>
                  <DateInput v-model="metaForm.date" />
                </div>
                <div>
                  <label class="label">Tổng giờ</label>
                  <DecimalHoursInput
                    v-model="metaForm.total_hours"
                    placeholder="2,5"
                  />
                </div>
                <div>
                  <label class="label">Giờ bắt đầu</label>
                  <TimeInput v-model="metaForm.start_time" />
                </div>
                <div>
                  <label class="label">Giờ kết thúc</label>
                  <TimeInput v-model="metaForm.end_time" />
                </div>
                <div class="sm:col-span-2 flex justify-stretch sm:justify-end">
                  <button
                    type="button"
                    class="btn-primary h-10 w-full gap-1.5 px-5 text-sm sm:w-auto"
                    :disabled="metaForm.processing"
                    @click="saveMeta"
                  >
                    <AppIcon
                      name="save"
                      :size="15"
                    />
                    Lưu thông tin
                  </button>
                </div>
              </div>
              <dl
                v-else
                class="divide-y divide-slate-100 rounded-lg border border-slate-100"
              >
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                  <dt class="text-xs font-medium text-slate-500">
                    Tên buổi học
                  </dt>
                  <dd class="text-sm text-slate-800 sm:col-span-2">
                    {{ session.title || '—' }}
                  </dd>
                </div>
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                  <dt class="text-xs font-medium text-slate-500">
                    Ngày học
                  </dt>
                  <dd class="text-sm text-slate-800 sm:col-span-2">
                    {{ fmtDate(session.date) }}
                  </dd>
                </div>
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                  <dt class="text-xs font-medium text-slate-500">
                    Tổng giờ
                  </dt>
                  <dd class="text-sm text-slate-800 sm:col-span-2">
                    {{ session.total_hours != null ? fmtHours(session.total_hours) : '—' }}
                  </dd>
                </div>
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                  <dt class="text-xs font-medium text-slate-500">
                    Khung giờ
                  </dt>
                  <dd class="text-sm text-slate-800 sm:col-span-2">
                    {{ sessionTimeLabel || '—' }}
                  </dd>
                </div>
              </dl>
            </div>

            <!-- Nội dung -->
            <div
              v-show="activeTab === 'content'"
              class="mx-auto max-w-3xl"
            >
              <div
                v-if="isEditing"
                class="space-y-4"
              >
                <KbRichTextField
                  v-model="contentForm.content"
                  label=""
                />
                <div class="flex justify-stretch sm:justify-end">
                  <button
                    type="button"
                    class="btn-primary h-10 w-full gap-1.5 px-5 text-sm sm:w-auto"
                    :disabled="contentForm.processing"
                    @click="saveContent"
                  >
                    <AppIcon
                      name="save"
                      :size="15"
                    />
                    Lưu nội dung
                  </button>
                </div>
              </div>
              <div
                v-else-if="hasContent"
                class="rich-content prose prose-sm max-w-none rounded-lg border border-slate-100 bg-white p-4 sm:p-6"
                v-html="session.content"
              />
              <div
                v-else
                class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-6 py-14 text-center"
              >
                <AppIcon
                  name="documents"
                  :size="32"
                  class="text-slate-300"
                />
                <p class="mt-3 text-sm font-medium text-slate-600">
                  Chưa có nội dung chi tiết
                </p>
                <p class="mt-1 max-w-sm text-xs text-slate-500">
                  <template v-if="canEdit">
                    Bấm «Chỉnh sửa» trên header để thêm ghi chú hoặc outline.
                  </template>
                  <template v-else>
                    Coach sẽ bổ sung ghi chú, outline hoặc tài liệu lý thuyết tại đây.
                  </template>
                </p>
              </div>
            </div>

            <!-- Tài liệu -->
            <div
              v-show="activeTab === 'materials'"
              class="mx-auto max-w-3xl space-y-4"
            >
              <ul
                v-if="session.materials?.length"
                class="space-y-3"
              >
                <li
                  v-for="m in session.materials"
                  :key="m.id"
                  class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm"
                >
                  <div class="flex flex-wrap items-center gap-2 border-b border-slate-50 px-4 py-3">
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600">
                      {{ m.type_label }}
                    </span>
                    <span class="min-w-0 flex-1 text-sm font-semibold text-slate-800">
                      {{ m.title }}
                    </span>
                  </div>
                  <div class="p-4">
                    <CoachingMaterialEmbed
                      v-if="m.embedAllowed && m.embedSrc"
                      :url="m.url"
                      :embed-src="m.embedSrc"
                      :title="m.title"
                    />
                    <a
                      v-else-if="m.url"
                      :href="m.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm font-medium text-brand hover:bg-brand-50/50"
                    >
                      <AppIcon
                        name="link"
                        :size="14"
                      />
                      Mở liên kết
                    </a>
                    <a
                      v-else-if="m.file_url"
                      :href="m.file_url"
                      class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm font-medium text-brand hover:bg-brand-50/50"
                    >
                      <AppIcon
                        name="download"
                        :size="14"
                      />
                      Tải file
                    </a>
                  </div>
                </li>
              </ul>
              <div
                v-else
                class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-6 py-12 text-center"
              >
                <AppIcon
                  name="link"
                  :size="32"
                  class="text-slate-300"
                />
                <p class="mt-3 text-sm font-medium text-slate-600">
                  Chưa có tài liệu
                </p>
              </div>

              <form
                v-if="isEditing"
                class="space-y-3 rounded-xl border border-dashed border-brand/25 bg-brand/[0.03] p-4 sm:p-5"
                @submit.prevent="submitMaterial"
              >
                <p class="text-sm font-semibold text-slate-800">
                  Thêm tài liệu (chỉnh sửa)
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label class="label">Loại</label>
                    <select
                      v-model="materialForm.type"
                      class="input w-full text-sm"
                    >
                      <option
                        v-for="mt in materialTypes"
                        :key="mt.value"
                        :value="mt.value"
                      >
                        {{ mt.label }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="label">Tiêu đề</label>
                    <input
                      v-model="materialForm.title"
                      class="input w-full"
                      placeholder="Tiêu đề hiển thị"
                      required
                    >
                  </div>
                </div>
                <div>
                  <label class="label">URL hoặc file</label>
                  <input
                    v-model="materialForm.url"
                    class="input w-full"
                    placeholder="YouTube, Canva, Loom, Google…"
                  >
                  <input
                    type="file"
                    class="input mt-2 w-full text-sm"
                    @change="materialForm.file = $event.target.files?.[0] ?? null"
                  >
                </div>
                <p
                  v-if="materialForm.errors.url"
                  class="text-xs text-danger"
                >
                  {{ materialForm.errors.url }}
                </p>
                <div class="flex justify-stretch sm:justify-end">
                  <button
                    type="submit"
                    class="btn-primary h-10 w-full gap-1.5 px-5 text-sm sm:w-auto"
                    :disabled="materialForm.processing"
                  >
                    <AppIcon
                      name="add"
                      :size="15"
                    />
                    Thêm tài liệu
                  </button>
                </div>
              </form>
            </div>

            <CoachingSessionAssignmentsTab
              v-show="activeTab === 'assignments'"
              :session-id="session.id"
              :assignments="session.assignments ?? []"
              :can-manage="can.manageAssignments === true"
              :can-complete="can.completeAssignments === true"
            />
          </div>
        </section>
      </div>
    </CoachingWorkspace>
  </AppLayout>
</template>
