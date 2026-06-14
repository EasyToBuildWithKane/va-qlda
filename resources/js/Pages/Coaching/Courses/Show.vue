<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';

const props = defineProps({
    course: { type: Object, required: true },
    sessionStatuses: { type: Array, default: () => [] },
});

const sessionForm = useForm({
    title: '',
    session_number: (props.course.sessions?.length ?? 0) + 1,
    date: '',
    total_hours: '',
});

function addSession() {
    sessionForm.post(`/coaching/courses/${props.course.id}/sessions`, {
        preserveScroll: true,
        onSuccess: () => sessionForm.reset('title', 'date', 'total_hours'),
    });
}
</script>

<template>
  <Head :title="course.name" />
  <AppLayout>
    <PageHeader
      :title="course.name"
      :subtitle="course.code"
    >
      <Link
        v-if="course.can?.update"
        :href="`/coaching/courses/${course.id}/edit`"
        class="btn-ghost h-9 px-3 text-sm"
      >
        Sửa khóa
      </Link>
    </PageHeader>

    <div class="mb-4 card p-4">
      <p class="text-sm text-slate-600">
        Tiến độ: <strong>{{ course.progress_percent }}%</strong>
        · {{ course.sessions_count ?? course.sessions?.length ?? 0 }} buổi
      </p>
      <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          class="h-full bg-brand"
          :style="{ width: `${course.progress_percent}%` }"
        />
      </div>
    </div>

    <div
      v-if="course.can?.update"
      class="card mb-4 p-4"
    >
      <h2 class="mb-3 text-sm font-semibold text-slate-700">
        Thêm buổi học
      </h2>
      <form
        class="flex flex-wrap gap-2"
        @submit.prevent="addSession"
      >
        <input
          v-model="sessionForm.title"
          class="input min-w-[12rem] flex-1"
          placeholder="Tên buổi"
          required
        >
        <input
          v-model.number="sessionForm.session_number"
          type="number"
          class="input w-20"
          min="1"
          required
        >
        <input
          v-model="sessionForm.date"
          type="date"
          class="input"
        >
        <input
          v-model="sessionForm.total_hours"
          type="number"
          class="input w-24"
          placeholder="Giờ"
          step="0.5"
        >
        <button
          type="submit"
          class="btn-primary h-9 px-3 text-sm"
          :disabled="sessionForm.processing"
        >
          Thêm
        </button>
      </form>
    </div>

    <ul class="space-y-2">
      <li
        v-for="s in course.sessions"
        :key="s.id"
      >
        <Link
          :href="`/coaching/sessions/${s.id}`"
          class="card flex items-center justify-between p-4 hover:border-brand/30"
        >
          <div>
            <span class="text-xs text-slate-400">Buổi {{ s.session_number }}</span>
            <p class="font-medium text-slate-800">
              {{ s.title }}
            </p>
          </div>
          <Badge
            v-if="s.status"
            :label="s.status.label"
            color="slate"
          />
        </Link>
      </li>
    </ul>
  </AppLayout>
</template>
