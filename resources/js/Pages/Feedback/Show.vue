<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import FeedbackFormModal from '@/modules/project/components/FeedbackFormModal.vue';
import { useDialog } from '@/composables/useDialog';
import { date } from '@/composables/useFormat';

const props = defineProps({
    feedback: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const changeStatus = (e) => router.put(`/feedback/${props.feedback.id}`, { status: e.target.value }, { preserveScroll: true });

const remove = async () => {
    if (!props.feedback.can?.delete) return;
    const ok = await dialog.confirm({
        title: 'Xoá phản hồi',
        message: `Xoá phản hồi «${props.feedback.title}»? Hành động không thể hoàn tác.`,
        tone: 'danger',
        confirmText: 'Xoá',
    });
    if (ok) {
        router.delete(`/feedback/${props.feedback.id}`);
    }
};
</script>

<template>
  <Head :title="feedback.code + ' — ' + feedback.title" />
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2">
        <Link
          href="/feedback"
          class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100"
        >
          <AppIcon
            name="back"
            :size="18"
          />
        </Link>
        <span class="font-mono text-sm text-slate-400">{{ feedback.code }}</span>
        <h1 class="font-display font-semibold text-slate-800">
          {{ feedback.title }}
        </h1>
      </div>
    </template>

    <div class="grid gap-5 lg:grid-cols-3">
      <div class="space-y-5 lg:col-span-2">
        <div class="card p-5">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <Badge
              :label="feedback.category.label"
              :color="feedback.category.color"
            />
            <Badge
              :label="feedback.priority.label"
              :color="feedback.priority.color"
            />
            <Badge
              :label="feedback.status.label"
              :color="feedback.status.color"
            />
            <span
              v-if="feedback.rating"
              class="text-sm text-amber-500"
            >{{ '★'.repeat(feedback.rating) }}<span class="text-slate-300">{{ '★'.repeat(5 - feedback.rating) }}</span></span>
            <div class="ml-auto flex flex-wrap items-center gap-1">
              <button
                v-if="feedback.can?.update"
                type="button"
                class="btn-ghost text-sm"
                @click="modal = true"
              >
                <AppIcon
                  name="edit"
                  :size="15"
                /> Sửa
              </button>
              <button
                v-if="feedback.can?.delete"
                type="button"
                class="btn-ghost text-sm text-rose-600 hover:bg-rose-50"
                @click="remove"
              >
                <AppIcon
                  name="delete"
                  :size="15"
                /> Xoá
              </button>
            </div>
          </div>
          <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
            {{ feedback.description }}
          </p>
        </div>

        <div class="card p-5">
          <CommentThread
            :comments="feedback.comments || []"
            commentable-type="feedback"
            :commentable-id="feedback.id"
          />
        </div>
      </div>

      <div class="space-y-4">
        <div class="card space-y-3 p-5 text-sm">
          <div v-if="feedback.can?.update">
            <label class="label">Trạng thái</label>
            <select
              :value="feedback.status.value"
              class="input"
              @change="changeStatus"
            >
              <option
                v-for="o in options.status"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Dự án</span><span class="font-medium text-slate-700">{{ feedback.project?.name || '—' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Người xử lý</span>
            <span
              v-if="feedback.assignee"
              class="flex items-center gap-1.5"
            ><Avatar
              :name="feedback.assignee.name"
              :src="feedback.assignee.avatar_path"
              :size="22"
            /> <span class="font-medium text-slate-700">{{ feedback.assignee.name }}</span></span>
            <span
              v-else
              class="text-slate-400"
            >Chưa giao</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Người gửi</span><span class="font-medium text-slate-700">{{ feedback.reporter_display }}</span>
          </div>
          <div
            v-if="feedback.reporter_email"
            class="flex justify-between"
          >
            <span class="text-slate-400">Email</span><span class="text-slate-700">{{ feedback.reporter_email }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Tạo lúc</span><span class="text-slate-700">{{ date(feedback.created_at) }}</span>
          </div>
          <div
            v-if="feedback.resolved_at"
            class="flex justify-between"
          >
            <span class="text-slate-400">Đã xử lý</span><span class="text-slate-700">{{ date(feedback.resolved_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <FeedbackFormModal
      :show="modal"
      :feedback="feedback"
      :projects="options.projects"
      :employees="options.employees"
      :category-options="options.category"
      :status-options="options.status"
      :priority-options="options.priority"
      @close="modal = false"
    />
  </AppLayout>
</template>
