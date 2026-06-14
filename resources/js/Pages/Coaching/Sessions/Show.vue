<script setup>
/* eslint-disable vue/no-v-html */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import DecimalHoursInput from '@/shared/ui/DecimalHoursInput.vue';
import CoachingWorkspace from '@/modules/coaching/components/CoachingWorkspace.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import CoachingMaterialEmbed from '@/modules/coaching/components/CoachingMaterialEmbed.vue';
import { useToast } from '@/shared/composables/useToast';
import { date as fmtDate, hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    session: { type: Object, required: true },
    course: { type: Object, required: true },
    materialTypes: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();

const STATUS_LABEL = {
    pending: 'Chưa học', in_progress: 'Đang học', completed: 'Hoàn thành', cancelled: 'Hủy',
};
const STATUS_COLOR = {
    pending: 'slate', in_progress: 'amber', completed: 'emerald', cancelled: 'rose',
};
const ASSIGN_STATUS_LABEL = {
    todo: 'Cần làm', doing: 'Đang làm', review: 'Chờ duyệt', done: 'Hoàn thành',
};
const ASSIGN_STATUS_COLOR = {
    todo: 'slate', doing: 'amber', review: 'sky', done: 'emerald',
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
    if (s && e) return `${s} – ${e}`;
    if (s) return `Bắt đầu ${s}`;
    if (e) return `Kết thúc ${e}`;
    return null;
});

const metaForm = useForm({
    date: props.session.date ?? '',
    total_hours: props.session.total_hours ?? null,
    topic: props.session.topic ?? '',
    start_time: props.session.start_time ?? '',
    end_time: props.session.end_time ?? '',
});

const materialForm = useForm({ type: 'youtube', title: '', url: '', file: null });
const assignmentForm = useForm({ title: '', description: '', deadline: '' });
const contentForm = useForm({ content: props.session.content ?? '' });

function saveMeta() {
    metaForm.patch(`/coaching/sessions/${props.session.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã lưu thông tin buổi học.'),
    });
}

function saveContent() {
    contentForm.patch(`/coaching/sessions/${props.session.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã lưu nội dung.'),
    });
}

function submitMaterial() {
    materialForm.post(`/coaching/sessions/${props.session.id}/materials`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { materialForm.reset(); toast.success('Đã thêm tài liệu.'); },
    });
}

function submitAssignment() {
    assignmentForm.post(`/coaching/sessions/${props.session.id}/assignments`, {
        preserveScroll: true,
        onSuccess: () => { assignmentForm.reset(); toast.success('Đã thêm bài tập.'); },
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
                {{ session.topic || 'Chưa có chủ đề' }}
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

          <div class="p-4 sm:p-6">
            <!-- Tổng quan -->
            <div
              v-show="activeTab === 'overview'"
              class="mx-auto max-w-3xl"
            >
              <p class="mb-4 text-sm text-slate-600">
                Ngày, giờ và chủ đề buổi học. Thông tin tóm tắt phía trên luôn phản ánh dữ liệu đã lưu.
              </p>
              <div
                v-if="can.update"
                class="grid gap-4 sm:grid-cols-2"
              >
                <div>
                  <label class="label">Ngày học</label>
                  <input
                    v-model="metaForm.date"
                    type="date"
                    class="input w-full"
                  >
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
                  <input
                    v-model="metaForm.start_time"
                    type="time"
                    class="input w-full"
                  >
                </div>
                <div>
                  <label class="label">Giờ kết thúc</label>
                  <input
                    v-model="metaForm.end_time"
                    type="time"
                    class="input w-full"
                  >
                </div>
                <div class="sm:col-span-2">
                  <label class="label">Chủ đề</label>
                  <input
                    v-model="metaForm.topic"
                    type="text"
                    class="input w-full"
                    placeholder="Chủ đề chính của buổi học"
                  >
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
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                  <dt class="text-xs font-medium text-slate-500">
                    Chủ đề
                  </dt>
                  <dd class="text-sm text-slate-800 sm:col-span-2">
                    {{ session.topic || '—' }}
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
                v-if="can.update"
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
                  Coach sẽ bổ sung ghi chú, outline hoặc tài liệu lý thuyết tại đây.
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
                v-if="can.update"
                class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5"
                @submit.prevent="submitMaterial"
              >
                <p class="text-sm font-semibold text-slate-800">
                  Thêm tài liệu
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

            <!-- Bài tập -->
            <div
              v-show="activeTab === 'assignments'"
              class="mx-auto max-w-3xl space-y-4"
            >
              <ul
                v-if="session.assignments?.length"
                class="space-y-2"
              >
                <li
                  v-for="a in session.assignments"
                  :key="a.id"
                  class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">
                      {{ a.title }}
                    </p>
                    <p
                      v-if="a.deadline"
                      class="mt-0.5 flex items-center gap-1 text-xs text-slate-500"
                    >
                      <AppIcon
                        name="calendar"
                        :size="12"
                      />
                      Hạn {{ fmtDate(a.deadline) }}
                    </p>
                  </div>
                  <Badge
                    class="self-start sm:self-center"
                    :label="ASSIGN_STATUS_LABEL[a.status] || a.status"
                    :color="ASSIGN_STATUS_COLOR[a.status] || 'slate'"
                  />
                </li>
              </ul>
              <div
                v-else
                class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-6 py-12 text-center"
              >
                <AppIcon
                  name="task"
                  :size="32"
                  class="text-slate-300"
                />
                <p class="mt-3 text-sm font-medium text-slate-600">
                  Chưa có bài tập
                </p>
              </div>

              <form
                v-if="can.update"
                class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5"
                @submit.prevent="submitAssignment"
              >
                <p class="text-sm font-semibold text-slate-800">
                  Thêm bài tập
                </p>
                <div>
                  <label class="label">Tiêu đề</label>
                  <input
                    v-model="assignmentForm.title"
                    class="input w-full"
                    placeholder="Tên bài tập"
                    required
                  >
                </div>
                <div>
                  <label class="label">Mô tả</label>
                  <textarea
                    v-model="assignmentForm.description"
                    class="input w-full"
                    rows="3"
                    placeholder="Yêu cầu, ghi chú (tuỳ chọn)"
                  />
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                  <div class="w-full sm:max-w-[11rem]">
                    <label class="label">Hạn nộp</label>
                    <input
                      v-model="assignmentForm.deadline"
                      type="date"
                      class="input w-full"
                    >
                  </div>
                  <button
                    type="submit"
                    class="btn-primary h-10 w-full gap-1.5 px-5 text-sm sm:w-auto"
                    :disabled="assignmentForm.processing"
                  >
                    <AppIcon
                      name="add"
                      :size="15"
                    />
                    Thêm bài tập
                  </button>
                </div>
              </form>
            </div>
          </div>
        </section>
      </div>
    </CoachingWorkspace>
  </AppLayout>
</template>
