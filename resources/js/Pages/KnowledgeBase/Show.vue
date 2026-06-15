<script setup>
/* eslint-disable vue/no-v-html -- article HTML from TipTap */
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import RichContentBody from '@/shared/ui/RichContentBody.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import { useCommentThreadPoll } from '@/composables/useCommentThreadPoll';
import { date } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
    toc: { type: Array, default: () => [] },
    related: { type: Array, default: () => [] },
    breadcrumb: { type: Array, default: () => [] },
});

useCommentThreadPoll({
    active: computed(() => true),
    enabled: computed(() => true),
    subscribed: computed(() => false),
    reloadKeys: computed(() => ['article']),
});

const tocItems = computed(() => (props.toc?.length ? props.toc : []));

const headerSubtitle = computed(() => {
    const parts = [];
    if (props.article.category?.name) parts.push(props.article.category.name);
    if (props.article.author?.full_name) parts.push(props.article.author.full_name);
    if (props.article.published_at) parts.push(date(props.article.published_at));
    return parts.join(' · ') || 'Bài viết tri thức nội bộ';
});

function toggleFavorite() {
    router.post(`/knowledge-base/articles/${props.article.slug}/favorite`, {}, { preserveScroll: true });
}

function markRead() {
    router.post(`/knowledge-base/articles/${props.article.slug}/read`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="article.title" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="article.title"
        :subtitle="headerSubtitle"
        icon="knowledge"
        icon-color="brand"
        back-href="/knowledge-base"
      >
        <div class="flex shrink-0 items-center gap-1.5">
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-2.5 text-xs sm:px-3 sm:text-sm"
            :title="article.is_favorite ? 'Bỏ yêu thích' : 'Yêu thích'"
            @click="toggleFavorite"
          >
            <AppIcon
              name="star"
              :size="15"
            />
            <span class="hidden sm:inline">{{ article.is_favorite ? 'Đã lưu' : 'Yêu thích' }}</span>
          </button>
          <button
            v-if="!article.is_read"
            type="button"
            class="btn-ghost hidden h-9 items-center gap-1.5 px-3 text-sm sm:inline-flex"
            @click="markRead"
          >
            <AppIcon
              name="check"
              :size="15"
            />
            Đã đọc
          </button>
          <Link
            v-if="article.can?.update"
            :href="`/knowledge-base/articles/${article.slug}/edit`"
            class="btn-ghost inline-flex h-9 w-9 items-center justify-center p-0"
            title="Chỉnh sửa bài viết"
            aria-label="Chỉnh sửa bài viết"
          >
            <AppIcon
              name="edit"
              :size="16"
            />
          </Link>
        </div>
      </PageHeader>
    </template>

    <nav
      class="mb-4 flex flex-wrap items-center gap-1 text-xs text-slate-400"
      aria-label="Đường dẫn"
    >
      <template
        v-for="(crumb, idx) in breadcrumb"
        :key="idx"
      >
        <Link
          v-if="crumb.href"
          :href="crumb.href"
          class="hover:text-brand"
        >
          {{ crumb.label }}
        </Link>
        <span
          v-else
          class="text-slate-500"
        >{{ crumb.label }}</span>
        <span v-if="idx < breadcrumb.length - 1">/</span>
      </template>
    </nav>

    <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
      <article class="min-w-0 flex-1">
        <div class="overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm">
          <div class="border-b border-slate-100 bg-gradient-to-b from-slate-50/80 to-white px-5 py-6 sm:px-8 sm:py-8">
            <div class="mb-4 flex flex-wrap items-center gap-2">
              <Badge
                v-if="article.status"
                :label="article.status.label"
                color="slate"
              />
              <span
                v-if="article.category"
                class="text-sm text-slate-500"
              >
                {{ article.category.name }}
              </span>
            </div>
            <h1 class="font-display text-2xl font-semibold leading-tight text-slate-900 sm:text-3xl">
              {{ article.title }}
            </h1>
            <div
              v-if="article.tags?.length"
              class="mt-4 flex flex-wrap gap-1.5"
            >
              <span
                v-for="t in article.tags"
                :key="t.id"
                class="inline-flex items-center rounded-full bg-brand/5 px-2.5 py-0.5 text-[11px] font-medium text-brand/90"
              >
                #{{ t.name }}
              </span>
            </div>
            <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
              <span
                v-if="article.author"
                class="inline-flex items-center gap-1"
              >
                <AppIcon
                  name="account"
                  :size="14"
                  class="text-slate-400"
                />
                {{ article.author.full_name }}
              </span>
              <span
                v-if="article.published_at"
                class="inline-flex items-center gap-1"
              >
                <AppIcon
                  name="calendar"
                  :size="14"
                  class="text-slate-400"
                />
                {{ date(article.published_at) }}
              </span>
              <span class="inline-flex items-center gap-1">
                <AppIcon
                  name="eye"
                  :size="14"
                  class="text-slate-400"
                />
                {{ article.view_count }} lượt xem
              </span>
            </p>
            <div
              v-if="article.excerpt?.trim()"
              class="mt-6 border-l-2 border-brand/35 pl-4"
            >
              <RichContentBody
                :content="article.excerpt"
                empty-text=""
                html-class="prose prose-sm max-w-none text-slate-600 italic"
                plain-class="text-sm italic leading-relaxed text-slate-600"
              />
            </div>
          </div>

          <div class="px-5 py-8 sm:px-8 sm:py-10">
            <div
              class="rich-content prose prose-base max-w-none text-slate-700"
              v-html="article.content"
            />
          </div>

          <div
            v-if="article.gallery_images?.length"
            class="border-t border-slate-100 px-5 py-6 sm:px-8"
          >
            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-400">
              Thư viện ảnh
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
              <figure
                v-for="img in article.gallery_images"
                :key="img.id"
              >
                <img
                  :src="img.url"
                  :alt="img.alt_text || img.original_name"
                  class="max-h-72 w-full rounded-lg object-cover ring-1 ring-slate-200/80"
                  loading="lazy"
                >
                <figcaption
                  v-if="img.alt_text"
                  class="mt-2 text-xs text-slate-500"
                >
                  {{ img.alt_text }}
                </figcaption>
              </figure>
            </div>
          </div>

          <ul
            v-if="article.attachments?.length"
            class="space-y-2 border-t border-slate-100 px-5 py-6 text-sm sm:px-8"
          >
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
              Tệp đính kèm
            </p>
            <li
              v-for="att in article.attachments"
              :key="att.id"
            >
              <a
                :href="att.url"
                class="inline-flex items-center gap-1.5 text-brand hover:underline"
                target="_blank"
                rel="noopener"
              >
                <AppIcon
                  name="documents"
                  :size="15"
                />
                {{ att.original_name }}
              </a>
            </li>
          </ul>
        </div>

        <div class="mt-8 rounded-card border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
          <CommentThread
            :comments="article.comments || []"
            commentable-type="kb_article"
            :commentable-id="article.id"
            heading="Bình luận"
            empty-message="Chưa có bình luận nào."
            delete-dialog-title="Xoá bình luận"
            delete-button-title="Xoá bình luận"
            realtime-hint="Bình luận mới sẽ hiện ngay không cần tải lại trang"
            placeholder="Viết bình luận…"
          />
        </div>
      </article>

      <aside class="w-full shrink-0 space-y-4 lg:sticky lg:top-4 lg:w-60">
        <div
          v-if="tocItems.length"
          class="rounded-card border border-slate-200/80 bg-white p-4 text-sm shadow-sm"
        >
          <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Mục lục
          </p>
          <ul class="space-y-1.5 border-l border-slate-200 pl-3">
            <li
              v-for="h in tocItems"
              :key="h.id"
              :class="h.level === 3 ? 'pl-2' : ''"
            >
              <a
                :href="`#${h.id}`"
                class="text-slate-600 hover:text-brand"
              >{{ h.text }}</a>
            </li>
          </ul>
        </div>
        <div
          v-if="related.length"
          class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
        >
          <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Bài liên quan
          </p>
          <ul class="space-y-2.5 text-sm">
            <li
              v-for="r in related"
              :key="r.id"
            >
              <Link
                :href="`/knowledge-base/articles/${r.slug}`"
                class="font-medium text-slate-700 hover:text-brand"
              >
                {{ r.title }}
              </Link>
            </li>
          </ul>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>
