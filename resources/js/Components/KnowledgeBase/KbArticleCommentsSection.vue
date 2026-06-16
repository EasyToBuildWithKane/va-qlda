<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';

const props = defineProps({
    comments: { type: Array, default: () => [] },
    articleId: { type: [Number, String], required: true },
    articleTitle: { type: String, default: '' },
});

const sortOrder = ref('newest');

const commentCount = computed(() => (props.comments || []).length);

const sortOptions = [
    { value: 'newest', label: 'Mới nhất' },
    { value: 'oldest', label: 'Cũ nhất' },
];
</script>

<template>
  <section
    id="comments"
    class="kb-comments scroll-mt-28"
    aria-label="Thảo luận bài viết"
  >
    <div class="relative overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm dark:border-slate-800 dark:from-slate-900/60 dark:to-slate-900/40">
      <div
        class="pointer-events-none absolute inset-0 opacity-40"
        aria-hidden="true"
      >
        <div class="absolute -right-16 -top-20 h-48 w-48 rounded-full bg-brand/[0.06] blur-2xl" />
        <div class="absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-sky-500/[0.05] blur-2xl" />
      </div>

      <header class="relative border-b border-slate-200/70 px-5 py-5 dark:border-slate-800 sm:px-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
              Thảo luận
            </p>
            <h2 class="mt-1 font-display text-xl font-semibold text-slate-900 dark:text-slate-50">
              Bình luận
              <span class="ml-1.5 tabular-nums text-base font-medium text-slate-400">
                ({{ commentCount }})
              </span>
            </h2>
            <p class="mt-1 max-w-xl text-sm text-slate-500 dark:text-slate-400">
              Chia sẻ góp nhìn, câu hỏi hoặc kinh nghiệm liên quan tới
              <span class="font-medium text-slate-700 dark:text-slate-300">{{ articleTitle || 'bài viết' }}</span>.
            </p>
          </div>

          <div
            class="flex shrink-0 flex-wrap items-center gap-2"
            role="group"
            aria-label="Sắp xếp bình luận"
          >
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Sắp xếp</span>
            <div class="inline-flex rounded-lg border border-slate-200/90 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-900/80">
              <button
                v-for="opt in sortOptions"
                :key="opt.value"
                type="button"
                class="rounded-md px-3 py-1.5 text-xs font-medium transition"
                :class="sortOrder === opt.value
                  ? 'bg-brand text-white shadow-sm'
                  : 'text-slate-600 hover:text-brand dark:text-slate-300'"
                :aria-pressed="sortOrder === opt.value"
                @click="sortOrder = opt.value"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
        </div>
      </header>

      <div class="relative px-5 py-6 sm:px-7 sm:py-7">
        <CommentThread
          variant="kb"
          hide-heading
          :comments="comments"
          commentable-type="kb_article"
          :commentable-id="articleId"
          :sort-order="sortOrder"
          :partial-reload-keys="['article']"
          placeholder="Viết bình luận của bạn… (Ctrl+Enter để gửi nhanh)"
          empty-message="Chưa có bình luận nào. Hãy mở đầu cuộc trao đổi — ý kiến của bạn giúp đồng nghiệp học hỏi thêm."
          delete-dialog-title="Xoá bình luận"
          delete-button-title="Xoá bình luận"
          realtime-hint="Bình luận mới từ đồng nghiệp hiện ngay — không cần tải lại trang."
        />
      </div>

      <footer
        v-if="commentCount > 0"
        class="relative flex items-center gap-2 border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400 sm:px-7"
      >
        <AppIcon
          name="info"
          :size="14"
          class="shrink-0 text-slate-400"
        />
        Chỉ bạn và quản trị viên có thể xoá bình luận của mình.
      </footer>
    </div>
  </section>
</template>
