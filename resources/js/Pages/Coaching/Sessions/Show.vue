<script setup>
/* eslint-disable vue/no-v-html */
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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

const statusValue = computed(() => props.session.status?.value ?? 'pending');

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

function markProgress(flag) {
    router.post('/coaching/progress', {
        course_id: props.course.id,
        session_id: props.session.id,
        [flag]: true,
    }, { preserveScroll: true, onSuccess: () => toast.success('Đã cập nhật tiến độ.') });
}

function updateStatus(e) {
    router.patch(`/coaching/sessions/${props.session.id}`, {
        status: e.target.value,
    }, { preserveScroll: true, onSuccess: () => toast.success('Đã cập nhật trạng thái.') });
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
      <div class="grid gap-5 xl:grid-cols-3">
        <!-- Main column -->
        <div class="space-y-5 xl:col-span-2">
          <!-- Thông tin buổi -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Thông tin buổi học
              </h2>
            </div>
            <div
              v-if="can.update"
              class="grid gap-4 p-5 sm:grid-cols-2"
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
              <div class="sm:col-span-2 flex justify-end">
                <button
                  type="button"
                  class="btn-primary h-9 gap-1.5 px-4 text-sm"
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
              class="grid gap-4 p-5 sm:grid-cols-2"
            >
              <div>
                <dt class="text-xs font-medium text-slate-500">
                  Ngày học
                </dt>
                <dd class="mt-1 text-sm text-slate-800">
                  {{ fmtDate(session.date) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-slate-500">
                  Tổng giờ
                </dt>
                <dd class="mt-1 text-sm text-slate-800">
                  {{ session.total_hours != null ? fmtHours(session.total_hours) : '—' }}
                </dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-slate-500">
                  Chủ đề
                </dt>
                <dd class="mt-1 text-sm text-slate-800">
                  {{ session.topic || '—' }}
                </dd>
              </div>
            </dl>
          </section>

          <!-- Nội dung -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Nội dung buổi học
              </h2>
            </div>
            <div class="p-5">
              <div
                v-if="can.update"
                class="space-y-3"
              >
                <KbRichTextField
                  v-model="contentForm.content"
                  label=""
                />
                <div class="flex justify-end">
                  <button
                    type="button"
                    class="btn-primary h-9 gap-1.5 px-4 text-sm"
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
                v-else-if="session.content"
                class="rich-content prose prose-sm max-w-none"
                v-html="session.content"
              />
              <p
                v-else
                class="text-sm text-slate-500"
              >
                Chưa có nội dung chi tiết.
              </p>
            </div>
          </section>

          <!-- Tài liệu -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Tài liệu
              </h2>
            </div>
            <div class="space-y-4 p-5">
              <div
                v-for="m in session.materials"
                :key="m.id"
                class="border-b border-slate-100 pb-4 last:border-0 last:pb-0"
              >
                <p class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-700">
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">{{ m.type_label }}</span>
                  {{ m.title }}
                </p>
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
                  class="inline-flex items-center gap-1 text-sm text-brand hover:underline"
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
                  class="inline-flex items-center gap-1 text-sm text-brand hover:underline"
                >
                  <AppIcon
                    name="download"
                    :size="14"
                  />
                  Tải file
                </a>
              </div>
              <p
                v-if="!session.materials?.length"
                class="text-sm text-slate-500"
              >
                Chưa có tài liệu.
              </p>

              <form
                v-if="can.update"
                class="space-y-2 border-t border-slate-100 pt-4"
                @submit.prevent="submitMaterial"
              >
                <div class="grid gap-2 sm:grid-cols-2">
                  <select
                    v-model="materialForm.type"
                    class="input w-full text-sm"
                  >
                    <option
                      v-for="t in materialTypes"
                      :key="t.value"
                      :value="t.value"
                    >
                      {{ t.label }}
                    </option>
                  </select>
                  <input
                    v-model="materialForm.title"
                    class="input w-full"
                    placeholder="Tiêu đề"
                    required
                  >
                </div>
                <input
                  v-model="materialForm.url"
                  class="input w-full"
                  placeholder="URL (YouTube, Canva, Loom, Google…)"
                >
                <input
                  type="file"
                  class="input w-full text-sm"
                  @change="materialForm.file = $event.target.files?.[0] ?? null"
                >
                <p
                  v-if="materialForm.errors.url"
                  class="text-xs text-danger"
                >
                  {{ materialForm.errors.url }}
                </p>
                <div class="flex justify-end">
                  <button
                    type="submit"
                    class="btn-primary h-9 gap-1.5 px-4 text-sm"
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
          </section>

          <!-- Bài tập -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Bài tập
              </h2>
            </div>
            <div class="space-y-3 p-5">
              <div
                v-for="a in session.assignments"
                :key="a.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2.5"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-slate-800">
                    {{ a.title }}
                  </p>
                  <p
                    v-if="a.deadline"
                    class="mt-0.5 text-xs text-slate-400"
                  >
                    Hạn: {{ fmtDate(a.deadline) }}
                  </p>
                </div>
                <Badge
                  :label="ASSIGN_STATUS_LABEL[a.status] || a.status"
                  :color="ASSIGN_STATUS_COLOR[a.status] || 'slate'"
                />
              </div>
              <p
                v-if="!session.assignments?.length"
                class="text-sm text-slate-500"
              >
                Chưa có bài tập.
              </p>

              <form
                v-if="can.update"
                class="space-y-2 border-t border-slate-100 pt-4"
                @submit.prevent="submitAssignment"
              >
                <input
                  v-model="assignmentForm.title"
                  class="input w-full"
                  placeholder="Tiêu đề bài tập"
                  required
                >
                <textarea
                  v-model="assignmentForm.description"
                  class="input w-full"
                  rows="2"
                  placeholder="Mô tả (tuỳ chọn)"
                />
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <input
                    v-model="assignmentForm.deadline"
                    type="date"
                    class="input h-9 w-44"
                  >
                  <button
                    type="submit"
                    class="btn-primary h-9 gap-1.5 px-4 text-sm"
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
          </section>
        </div>

        <!-- Aside -->
        <aside class="space-y-5">
          <section
            v-if="can.update"
            class="card overflow-hidden"
          >
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Trạng thái buổi
              </h2>
            </div>
            <div class="p-5">
              <select
                :value="statusValue"
                class="input w-full"
                @change="updateStatus"
              >
                <option value="pending">
                  Chưa học
                </option>
                <option value="in_progress">
                  Đang học
                </option>
                <option value="completed">
                  Hoàn thành
                </option>
                <option value="cancelled">
                  Hủy
                </option>
              </select>
            </div>
          </section>

          <section class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3.5">
              <h2 class="font-display text-sm font-semibold text-slate-800">
                Theo dõi học tập
              </h2>
            </div>
            <div class="flex flex-col gap-2 p-5">
              <button
                type="button"
                class="btn-ghost h-9 justify-start gap-2 px-3 text-sm"
                @click="markProgress('is_viewed')"
              >
                <AppIcon
                  name="eye"
                  :size="15"
                />
                Đã xem bài giảng
              </button>
              <button
                type="button"
                class="btn-ghost h-9 justify-start gap-2 px-3 text-sm"
                @click="markProgress('is_in_progress')"
              >
                <AppIcon
                  name="clock"
                  :size="15"
                />
                Đang học
              </button>
              <button
                type="button"
                class="btn-ghost h-9 justify-start gap-2 px-3 text-sm"
                @click="markProgress('is_completed')"
              >
                <AppIcon
                  name="done"
                  :size="15"
                />
                Hoàn thành buổi
              </button>
            </div>
          </section>
        </aside>
      </div>
    </CoachingWorkspace>
  </AppLayout>
</template>
