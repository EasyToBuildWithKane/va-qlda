<script setup>
/* eslint-disable vue/no-v-html */
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DecimalHoursInput from '@/shared/ui/DecimalHoursInput.vue';
import DateInput from '@/shared/ui/form/DateInput.vue';
import TimeInput from '@/shared/ui/form/TimeInput.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import CoachingSessionAssignmentsTab from '@/modules/coaching/components/CoachingSessionAssignmentsTab.vue';
import CoachingSessionMaterialsTab from '@/modules/coaching/components/CoachingSessionMaterialsTab.vue';
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

const contentForm = useForm({ content: props.session.content ?? '' });

const formsDirty = computed(
    () => metaForm.isDirty || contentForm.isDirty,
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

function tabBadge(key) {
    if (key === 'materials') return materialCount.value;
    if (key === 'assignments') return assignmentCount.value;
    return 0;
}
</script>

<template>
  <Head :title="`Buổi ${session.session_number}: ${session.title}`" />
  <AppLayout flush>
    <template #header>
      <PageHeader
        :title="`Buổi ${session.session_number}`"
        :subtitle="course.name"
        icon="weekly"
        :back-href="`/coaching/courses/${course.id}`"
      >
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

    <div class="flex h-[calc(100dvh-3.5rem)] min-h-0 flex-1 flex-col overflow-hidden p-3 sm:p-5">
      <CoachingWorkspace class="flex h-full min-h-0 flex-1 flex-col">
        <section class="card flex h-full min-h-0 flex-col overflow-hidden">
          <!-- Hero + KPI -->
          <div class="border-b border-slate-100 bg-gradient-to-br from-brand/[0.08] via-white to-slate-50/90 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-brand">
                  Buổi {{ session.session_number }}
                </p>
                <h1 class="mt-0.5 font-display text-xl font-semibold leading-snug text-slate-900 sm:text-2xl">
                  {{ session.title || 'Chưa có tên buổi' }}
                </h1>
                <Link
                  :href="route('coaching.courses.show', { course: course.id })"
                  class="mt-2 inline-flex items-center gap-1 text-sm text-slate-600 transition hover:text-brand"
                >
                  <span class="font-mono text-xs text-slate-400">{{ course.code }}</span>
                  <span>{{ course.name }}</span>
                  <AppIcon
                    name="chevron-right"
                    :size="14"
                    class="opacity-60"
                  />
                </Link>
              </div>
              <Badge
                class="hidden shrink-0 sm:inline-flex"
                :label="STATUS_LABEL[statusValue] || statusValue"
                :color="STATUS_COLOR[statusValue] || 'slate'"
              />
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
              <div class="rounded-xl border border-white/80 bg-white/90 px-3 py-3 shadow-sm ring-1 ring-slate-100/80">
                <dt class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                  <AppIcon
                    name="calendar"
                    :size="12"
                  />
                  Ngày học
                </dt>
                <dd class="mt-1.5 text-sm font-semibold tabular-nums text-slate-800">
                  {{ session.date ? fmtDate(session.date) : '—' }}
                </dd>
              </div>
              <div class="rounded-xl border border-white/80 bg-white/90 px-3 py-3 shadow-sm ring-1 ring-slate-100/80">
                <dt class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                  <AppIcon
                    name="worklog"
                    :size="12"
                  />
                  Tổng giờ
                </dt>
                <dd class="mt-1.5 text-sm font-semibold text-slate-800">
                  {{ session.total_hours != null ? fmtHours(session.total_hours) : '—' }}
                </dd>
              </div>
              <div class="rounded-xl border border-white/80 bg-white/90 px-3 py-3 shadow-sm ring-1 ring-slate-100/80">
                <dt class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                  <AppIcon
                    name="clock"
                    :size="12"
                  />
                  Khung giờ
                </dt>
                <dd class="mt-1.5 text-sm font-semibold text-slate-800">
                  {{ sessionTimeLabel || '—' }}
                </dd>
              </div>
              <div class="rounded-xl border border-white/80 bg-white/90 px-3 py-3 shadow-sm ring-1 ring-slate-100/80">
                <dt class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                  <AppIcon
                    name="task"
                    :size="12"
                  />
                  Tài liệu · Bài tập
                </dt>
                <dd class="mt-1.5 text-sm font-semibold tabular-nums text-slate-800">
                  {{ materialCount }}
                  <span class="font-normal text-slate-400">·</span>
                  {{ assignmentCount }}
                </dd>
              </div>
            </dl>
          </div>

          <nav
            class="sticky top-0 z-10 flex shrink-0 items-stretch gap-0 overflow-x-auto overscroll-x-contain border-b border-slate-200 bg-white/95 backdrop-blur-sm [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            aria-label="Nội dung buổi học"
          >
            <button
              v-for="t in tabs"
              :key="t.key"
              type="button"
              class="flex min-w-[5.5rem] shrink-0 items-center justify-center gap-1.5 border-b-2 px-4 py-3.5 text-sm font-semibold transition sm:min-w-0 sm:px-6"
              :class="activeTab === t.key
                ? 'border-brand bg-brand-50/40 text-brand'
                : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
              @click="activeTab = t.key"
            >
              <AppIcon
                :name="t.icon"
                :size="17"
                class="shrink-0 opacity-90"
              />
              <span>{{ t.label }}</span>
              <span
                v-if="tabBadge(t.key) > 0"
                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold"
                :class="activeTab === t.key ? 'bg-brand text-white' : 'bg-slate-200 text-slate-600'"
              >
                {{ tabBadge(t.key) }}
              </span>
            </button>
          </nav>

          <div
            v-if="isEditing"
            class="flex flex-wrap items-center justify-between gap-2 border-b border-brand/15 bg-brand-50/50 px-4 py-2.5 text-sm sm:px-6 lg:px-8"
          >
            <span class="font-medium text-brand">
              Đang chỉnh sửa buổi học
            </span>
            <span class="text-xs text-slate-600">
              Lưu từng tab hoặc thêm tài liệu; bài tập giao tại tab Bài tập.
            </span>
          </div>

          <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-50/30">
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain">
              <div
                class="flex min-h-[calc(100dvh-13.5rem)] w-full flex-col px-4 py-5 sm:min-h-[calc(100dvh-12.5rem)] sm:px-6 lg:px-8 lg:py-6"
              >
                <!-- Tổng quan -->
                <div
                  v-show="activeTab === 'overview'"
                  class="flex min-h-[calc(100dvh-15rem)] flex-1 flex-col"
                >
                  <p
                    v-if="isEditing"
                    class="mb-5 max-w-3xl text-sm text-slate-600"
                  >
                    Cập nhật tên buổi, ngày và khung giờ. Bấm «Lưu thông tin» rồi quay lại chế độ xem.
                  </p>
                  <p
                    v-else
                    class="mb-5 max-w-3xl text-sm text-slate-600"
                  >
                    Thông tin buổi học. Bấm «Chỉnh sửa» trên header để thay đổi.
                  </p>
                  <div
                    v-if="isEditing"
                    class="grid max-w-4xl gap-4 sm:grid-cols-2"
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
                    <div class="flex sm:col-span-2 sm:justify-end">
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
                  <div
                    v-else
                    class="grid flex-1 gap-4 lg:grid-cols-2 lg:items-stretch"
                  >
                    <dl class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm lg:min-h-[14rem]">
                      <div class="grid gap-1 px-5 py-3.5 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-xs font-medium text-slate-500">
                          Tên buổi học
                        </dt>
                        <dd class="text-sm font-medium text-slate-800 sm:col-span-2">
                          {{ session.title || '—' }}
                        </dd>
                      </div>
                      <div class="grid gap-1 px-5 py-3.5 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-xs font-medium text-slate-500">
                          Ngày học
                        </dt>
                        <dd class="text-sm text-slate-800 sm:col-span-2">
                          {{ fmtDate(session.date) }}
                        </dd>
                      </div>
                      <div class="grid gap-1 px-5 py-3.5 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-xs font-medium text-slate-500">
                          Tổng giờ
                        </dt>
                        <dd class="text-sm text-slate-800 sm:col-span-2">
                          {{ session.total_hours != null ? fmtHours(session.total_hours) : '—' }}
                        </dd>
                      </div>
                      <div class="grid gap-1 px-5 py-3.5 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-xs font-medium text-slate-500">
                          Khung giờ
                        </dt>
                        <dd class="text-sm text-slate-800 sm:col-span-2">
                          {{ sessionTimeLabel || '—' }}
                        </dd>
                      </div>
                    </dl>
                    <div class="flex min-h-[14rem] flex-col gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-5 lg:min-h-0 lg:flex-1">
                      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Chuyển nhanh
                      </p>
                      <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:border-brand/30 hover:text-brand"
                        @click="activeTab = 'content'"
                      >
                        <AppIcon
                          name="documents"
                          :size="18"
                          class="text-brand"
                        />
                        Nội dung buổi học
                      </button>
                      <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:border-brand/30 hover:text-brand"
                        @click="activeTab = 'materials'"
                      >
                        <AppIcon
                          name="link"
                          :size="18"
                          class="text-brand"
                        />
                        Tài liệu ({{ materialCount }})
                      </button>
                      <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:border-brand/30 hover:text-brand"
                        @click="activeTab = 'assignments'"
                      >
                        <AppIcon
                          name="task"
                          :size="18"
                          class="text-brand"
                        />
                        Bài tập ({{ assignmentCount }})
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Nội dung -->
                <div
                  v-show="activeTab === 'content'"
                  class="flex min-h-[calc(100dvh-15rem)] flex-1 flex-col"
                >
                  <div
                    v-if="isEditing"
                    class="flex flex-1 flex-col gap-5"
                  >
                    <KbRichTextField
                      v-model="contentForm.content"
                      label=""
                      enable-google-workspace-embed
                      editor-min-height-class="min-h-[calc(100dvh-20rem)] lg:min-h-[calc(100dvh-18rem)]"
                      placeholder="Ghi chú buổi học, outline, nhúng Google Sheet/Docs…"
                      hint="Dùng «Sheet» / «Docs» trên toolbar để xem trước full width. File Google cần quyền xem qua link."
                    />
                    <div class="flex justify-end border-t border-slate-100 pt-4">
                      <button
                        type="button"
                        class="btn-primary h-10 gap-1.5 px-6 text-sm"
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
                    class="rich-content prose prose-base min-h-[calc(100dvh-16rem)] flex-1 max-w-none rounded-xl border border-slate-100 bg-white px-5 py-6 shadow-sm sm:px-8 sm:py-8 lg:px-10"
                    v-html="session.content"
                  />
                  <div
                    v-else
                    class="flex min-h-[calc(100dvh-16rem)] flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-16 text-center"
                  >
                    <AppIcon
                      name="documents"
                      :size="36"
                      class="text-slate-300"
                    />
                    <p class="mt-3 text-sm font-medium text-slate-600">
                      Chưa có nội dung chi tiết
                    </p>
                    <p class="mt-1 max-w-md text-xs text-slate-500">
                      <template v-if="canEdit">
                        Bấm «Chỉnh sửa» để thêm outline, Sheet/Docs nhúng hoặc ghi chú.
                      </template>
                      <template v-else>
                        Coach sẽ bổ sung nội dung tại đây.
                      </template>
                    </p>
                  </div>
                </div>

                <CoachingSessionMaterialsTab
                  v-show="activeTab === 'materials'"
                  class="min-h-[calc(100dvh-15rem)] flex-1"
                  :session-id="session.id"
                  :materials="session.materials ?? []"
                  :material-types="materialTypes"
                  :is-editing="isEditing"
                />

                <CoachingSessionAssignmentsTab
                  v-show="activeTab === 'assignments'"
                  class="min-h-[calc(100dvh-15rem)] flex-1"
                  :session-id="session.id"
                  :assignments="session.assignments ?? []"
                  :can-manage="can.manageAssignments === true"
                  :can-complete="can.completeAssignments === true"
                />
              </div>
            </div>
          </div>
        </section>
      </CoachingWorkspace>
    </div>
  </AppLayout>
</template>
