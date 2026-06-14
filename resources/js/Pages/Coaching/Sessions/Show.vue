<script setup>
/* eslint-disable vue/no-v-html */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import CoachingMaterialEmbed from '@/modules/coaching/components/CoachingMaterialEmbed.vue';

const props = defineProps({
    session: { type: Object, required: true },
    course: { type: Object, required: true },
    materialTypes: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const materialForm = useForm({
    type: 'youtube',
    title: '',
    url: '',
    file: null,
});

const assignmentForm = useForm({
    title: '',
    description: '',
    deadline: '',
});

const contentForm = useForm({
    content: props.session.content ?? '',
});

function saveContent() {
    contentForm.patch(`/coaching/sessions/${props.session.id}`, { preserveScroll: true });
}

function submitMaterial() {
    materialForm.post(`/coaching/sessions/${props.session.id}/materials`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => materialForm.reset(),
    });
}

function submitAssignment() {
    assignmentForm.post(`/coaching/sessions/${props.session.id}/assignments`, {
        preserveScroll: true,
        onSuccess: () => assignmentForm.reset(),
    });
}

function markProgress(flag) {
    router.post('/coaching/progress', {
        course_id: props.course.id,
        session_id: props.session.id,
        [flag]: true,
    }, { preserveScroll: true });
}

function updateStatus(e) {
    router.patch(`/coaching/sessions/${props.session.id}`, {
        status: e.target.value,
    }, { preserveScroll: true });
}
</script>

<template>
  <Head :title="session.title" />
  <AppLayout>
    <template #header>
      <Link
        :href="`/coaching/courses/${course.id}`"
        class="text-xs text-brand hover:underline"
      >
        ← {{ course.name }}
      </Link>
      <h1 class="font-display text-lg font-semibold">
        Buổi {{ session.session_number }}: {{ session.title }}
      </h1>
    </template>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="space-y-4 lg:col-span-2">
        <div class="card p-5">
          <div
            v-if="can.update"
            class="mb-4"
          >
            <label class="label">Trạng thái buổi</label>
            <select
              :value="session.status?.value"
              class="input"
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
          <div
            v-if="can.update"
            class="mb-4 space-y-3 border-b border-slate-100 pb-4"
          >
            <KbRichTextField
              v-model="contentForm.content"
              label="Nội dung buổi học"
            />
            <button
              type="button"
              class="btn-primary text-sm"
              :disabled="contentForm.processing"
              @click="saveContent"
            >
              Lưu nội dung
            </button>
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

        <div class="card p-5">
          <h2 class="mb-3 text-sm font-semibold">
            Tài liệu
          </h2>
          <div
            v-for="m in session.materials"
            :key="m.id"
            class="mb-6 border-b border-slate-100 pb-4 last:border-0"
          >
            <p class="mb-2 text-sm font-medium text-slate-700">
              {{ m.type_label }}: {{ m.title }}
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
              class="text-sm text-brand hover:underline"
            >Mở liên kết</a>
            <a
              v-else-if="m.file_url"
              :href="m.file_url"
              class="text-sm text-brand hover:underline"
            >Tải file</a>
          </div>
          <ul
            v-if="!session.materials?.length"
            class="mb-4 text-sm text-slate-500"
          >
            <li>Chưa có tài liệu.</li>
          </ul>
          <form
            v-if="can.update"
            class="space-y-2 border-t border-slate-100 pt-4"
            @submit.prevent="submitMaterial"
          >
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
            <input
              v-model="materialForm.url"
              class="input w-full"
              placeholder="URL (YouTube, Canva, …)"
            >
            <input
              type="file"
              class="input w-full text-sm"
              @change="materialForm.file = $event.target.files?.[0] ?? null"
            >
            <button
              type="submit"
              class="btn-primary text-sm"
            >
              Thêm tài liệu
            </button>
          </form>
        </div>

        <div class="card p-5">
          <h2 class="mb-3 text-sm font-semibold">
            Bài tập
          </h2>
          <ul class="mb-4 space-y-2 text-sm">
            <li
              v-for="a in session.assignments"
              :key="a.id"
            >
              <strong>{{ a.title }}</strong>
              <span class="text-slate-400"> — {{ a.status }}</span>
            </li>
          </ul>
          <form
            v-if="can.update"
            class="space-y-2 border-t pt-4"
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
            />
            <input
              v-model="assignmentForm.deadline"
              type="date"
              class="input"
            >
            <button
              type="submit"
              class="btn-primary text-sm"
            >
              Thêm bài tập
            </button>
          </form>
        </div>
      </div>

      <aside class="space-y-3">
        <div class="card p-4 text-sm">
          <p class="label">
            Theo dõi học tập
          </p>
          <div class="mt-2 flex flex-col gap-2">
            <button
              type="button"
              class="btn-ghost text-xs"
              @click="markProgress('is_viewed')"
            >
              Đã xem bài giảng
            </button>
            <button
              type="button"
              class="btn-ghost text-xs"
              @click="markProgress('is_in_progress')"
            >
              Đang học
            </button>
            <button
              type="button"
              class="btn-ghost text-xs"
              @click="markProgress('is_completed')"
            >
              Hoàn thành buổi
            </button>
          </div>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>
