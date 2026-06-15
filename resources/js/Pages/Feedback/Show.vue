<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import { date, datetime } from '@/composables/useFormat';

const props = defineProps({
    feedback: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

// Soft tint + dot màu cho từng màu trạng thái — chuỗi literal để Tailwind giữ lại. + dot màu cho từng màu trạng thái — chuỗi literal để Tailwind giữ lại.
const ACCENTS = {
    slate: { dot: 'bg-slate-400', tint: 'bg-slate-50 border-slate-200', text: 'text-slate-700' },
    sky: { dot: 'bg-sky-500', tint: 'bg-sky-50 border-sky-200', text: 'text-sky-700' },
    violet: { dot: 'bg-violet-500', tint: 'bg-violet-50 border-violet-200', text: 'text-violet-700' },
    amber: { dot: 'bg-amber-500', tint: 'bg-amber-50 border-amber-200', text: 'text-amber-700' },
    emerald: { dot: 'bg-emerald-500', tint: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-700' },
    rose: { dot: 'bg-rose-500', tint: 'bg-rose-50 border-rose-200', text: 'text-rose-700' },
};

const statusAccent = computed(() => ACCENTS[props.feedback.status?.color] || ACCENTS.slate);
const isResolved = computed(() => ['resolved', 'declined'].includes(props.feedback.status?.value));
const ratingValue = computed(() => Number(props.feedback.rating) || 0);

const changeStatus = (e) => router.put(`/feedback/${props.feedback.id}`, { status: e.target.value }, { preserveScroll: true });
</script>

<template>
  <Head :title="feedback.code + ' — ' + feedback.title" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Chi tiết phản hồi"
        :subtitle="feedback.code"
        icon="feedback"
        icon-color="brand"
      />
    </template>

    <div class="grid gap-5 lg:grid-cols-3">
      <!-- ── Cột chính ─────────────────────────────────────────── -->
      <div class="space-y-5 lg:col-span-2">
        <!-- Tổng quan: trạng thái nổi bật + lưới thuộc tính có nhãn -->
        <section class="card overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
              {{ feedback.category.label }}
            </p>
            <h2 class="mt-0.5 font-display text-lg font-semibold leading-snug text-slate-800">
              {{ feedback.title }}
            </h2>
          </header>

          <!-- Banner trạng thái -->
          <div
            class="mx-5 mt-4 flex items-center gap-3 rounded-card border px-4 py-3"
            :class="statusAccent.tint"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/70"
            >
              <AppIcon
                :name="isResolved ? 'done' : 'clock'"
                :size="18"
                :class="statusAccent.text"
              />
            </span>
            <div class="min-w-0">
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Trạng thái xử lý
              </p>
              <p
                class="flex items-center gap-1.5 font-semibold"
                :class="statusAccent.text"
              >
                <span
                  class="h-2 w-2 rounded-full"
                  :class="statusAccent.dot"
                />
                {{ feedback.status.label }}
              </p>
            </div>
            <p
              v-if="isResolved && feedback.resolved_at"
              class="ml-auto hidden text-right text-xs text-slate-500 sm:block"
            >
              Hoàn tất<br>
              <span class="font-medium text-slate-700">{{ date(feedback.resolved_at) }}</span>
            </p>
          </div>

          <!-- Lưới thuộc tính có nhãn -->
          <dl class="grid grid-cols-2 gap-px overflow-hidden border-t border-slate-100 sm:grid-cols-3">
            <div class="bg-white px-5 py-3">
              <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Phân loại
              </dt>
              <dd class="mt-1">
                <Badge
                  :label="feedback.category.label"
                  :color="feedback.category.color"
                />
              </dd>
            </div>
            <div class="bg-white px-5 py-3">
              <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Mức ưu tiên
              </dt>
              <dd class="mt-1">
                <Badge
                  v-if="feedback.priority"
                  :label="feedback.priority.label"
                  :color="feedback.priority.color"
                />
                <span
                  v-else
                  class="text-sm text-slate-400"
                >Chưa đặt</span>
              </dd>
            </div>
            <div class="bg-white px-5 py-3">
              <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Đánh giá
              </dt>
              <dd class="mt-1 flex items-center gap-1.5 text-sm">
                <template v-if="ratingValue">
                  <span class="tracking-tight text-amber-500">
                    <span>{{ '★'.repeat(ratingValue) }}</span><span class="text-slate-200">{{ '★'.repeat(5 - ratingValue) }}</span>
                  </span>
                  <span class="tabular-nums text-slate-500">{{ ratingValue }}/5</span>
                </template>
                <span
                  v-else
                  class="text-slate-400"
                >Chưa đánh giá</span>
              </dd>
            </div>
          </dl>
        </section>

        <!-- Nội dung phản hồi -->
        <section class="card p-5">
          <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
            <AppIcon
              name="feedback"
              :size="16"
              class="text-slate-400"
            />
            Nội dung phản hồi
          </h3>
          <p
            v-if="feedback.description"
            class="whitespace-pre-wrap text-sm leading-relaxed text-slate-600"
          >
            {{ feedback.description }}
          </p>
          <p
            v-else
            class="text-sm italic text-slate-400"
          >
            Không có mô tả chi tiết.
          </p>
        </section>

        <!-- Bình luận -->
        <section class="card p-5">
          <CommentThread
            :comments="feedback.comments || []"
            commentable-type="feedback"
            :commentable-id="feedback.id"
          />
        </section>
      </div>

      <!-- ── Sidebar ───────────────────────────────────────────── -->
      <div class="space-y-4">
        <!-- Đổi trạng thái -->
        <div
          v-if="feedback.can?.update"
          class="card p-5"
        >
          <label
            for="feedback-status"
            class="label"
          >Cập nhật trạng thái</label>
          <select
            id="feedback-status"
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
          <p class="mt-2 text-xs text-slate-400">
            Đặt «Đã xử lý» hoặc «Từ chối» để khép phản hồi.
          </p>
        </div>

        <!-- Thông tin -->
        <div class="card divide-y divide-slate-100 text-sm">
          <p class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Thông tin
          </p>

          <div class="flex items-center justify-between gap-3 px-5 py-3">
            <span class="text-slate-400">Dự án</span>
            <Link
              v-if="feedback.project"
              :href="`/projects/${feedback.project.id}?tab=feedback`"
              class="flex min-w-0 items-center gap-1.5 font-medium text-slate-700 hover:text-brand"
            >
              <span
                class="h-2.5 w-2.5 shrink-0 rounded-full"
                :style="{ backgroundColor: feedback.project.color || '#94a3b8' }"
              />
              <span class="truncate">{{ feedback.project.name }}</span>
            </Link>
            <span
              v-else
              class="text-slate-400"
            >—</span>
          </div>

          <div class="flex items-start justify-between gap-3 px-5 py-3">
            <span class="pt-0.5 text-slate-400">Người gửi</span>
            <div class="min-w-0 text-right">
              <p class="font-medium text-slate-700">
                {{ feedback.reporter_display }}
              </p>
              <a
                v-if="feedback.reporter_email"
                :href="`mailto:${feedback.reporter_email}`"
                class="break-all text-xs text-slate-400 hover:text-brand"
              >{{ feedback.reporter_email }}</a>
            </div>
          </div>

          <div class="flex items-center justify-between gap-3 px-5 py-3">
            <span class="text-slate-400">Người xử lý</span>
            <span
              v-if="feedback.assignee"
              class="flex min-w-0 items-center gap-1.5"
            >
              <Avatar
                :name="feedback.assignee.name"
                :src="feedback.assignee.avatar_path"
                :size="22"
              />
              <span class="truncate font-medium text-slate-700">{{ feedback.assignee.name }}</span>
            </span>
            <span
              v-else
              class="text-slate-400"
            >Chưa giao</span>
          </div>
        </div>

        <!-- Mốc thời gian -->
        <div class="card divide-y divide-slate-100 text-sm">
          <p class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Mốc thời gian
          </p>
          <div class="flex items-center justify-between gap-3 px-5 py-3">
            <span class="flex items-center gap-1.5 text-slate-400">
              <AppIcon
                name="add"
                :size="14"
              /> Tạo lúc
            </span>
            <span class="text-slate-700">{{ datetime(feedback.created_at) }}</span>
          </div>
          <div
            v-if="feedback.updated_at"
            class="flex items-center justify-between gap-3 px-5 py-3"
          >
            <span class="flex items-center gap-1.5 text-slate-400">
              <AppIcon
                name="refresh"
                :size="14"
              /> Cập nhật
            </span>
            <span class="text-slate-700">{{ datetime(feedback.updated_at) }}</span>
          </div>
          <div
            v-if="feedback.resolved_at"
            class="flex items-center justify-between gap-3 px-5 py-3"
          >
            <span class="flex items-center gap-1.5 text-slate-400">
              <AppIcon
                name="done"
                :size="14"
              /> Đã xử lý
            </span>
            <span class="font-medium text-emerald-600">{{ datetime(feedback.resolved_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
