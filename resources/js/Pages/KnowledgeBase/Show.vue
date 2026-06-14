<script setup>
/* eslint-disable vue/no-v-html -- article HTML from TipTap */
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
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
      <nav class="mb-1 flex flex-wrap items-center gap-1 text-xs text-slate-400">
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
          <span v-else>{{ crumb.label }}</span>
          <span v-if="idx < breadcrumb.length - 1">/</span>
        </template>
      </nav>
      <h1 class="font-display text-lg font-semibold text-slate-800">
        {{ article.title }}
      </h1>
    </template>

    <div class="flex flex-col gap-6 lg:flex-row">
      <article class="min-w-0 flex-1 space-y-4">
        <div class="card p-5">
          <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <Badge
              v-if="article.status"
              :label="article.status.label"
              color="slate"
            />
            <span v-if="article.author">{{ article.author.full_name }}</span>
            <span v-if="article.published_at">· {{ date(article.published_at) }}</span>
            <span>· {{ article.view_count }} lượt xem</span>
            <div class="ml-auto flex gap-2">
              <button
                type="button"
                class="btn-ghost text-xs"
                @click="toggleFavorite"
              >
                <AppIcon
                  name="star"
                  :size="14"
                />
                {{ article.is_favorite ? 'Bỏ yêu thích' : 'Yêu thích' }}
              </button>
              <button
                v-if="!article.is_read"
                type="button"
                class="btn-ghost text-xs"
                @click="markRead"
              >
                Đánh dấu đã đọc
              </button>
            </div>
          </div>
          <div
            v-if="article.excerpt"
            class="mb-4 text-sm italic text-slate-500"
          >
            {{ article.excerpt }}
          </div>
          <div
            class="rich-content prose prose-sm max-w-none text-slate-700"
            v-html="article.content"
          />
          <div
            v-if="article.gallery_images?.length"
            class="mt-6 border-t border-slate-100 pt-4"
          >
            <p class="mb-3 text-xs font-semibold uppercase text-slate-400">
              Thư viện ảnh
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
              <figure
                v-for="img in article.gallery_images"
                :key="img.id"
              >
                <img
                  :src="img.url"
                  :alt="img.alt_text || img.original_name"
                  class="max-h-64 w-full rounded-lg object-cover"
                  loading="lazy"
                >
                <figcaption
                  v-if="img.alt_text"
                  class="mt-1 text-xs text-slate-500"
                >
                  {{ img.alt_text }}
                </figcaption>
              </figure>
            </div>
          </div>
          <ul
            v-if="article.attachments?.length"
            class="mt-6 space-y-2 border-t border-slate-100 pt-4 text-sm"
          >
            <li
              v-for="att in article.attachments"
              :key="att.id"
            >
              <a
                :href="att.url"
                class="text-brand hover:underline"
                target="_blank"
                rel="noopener"
              >{{ att.original_name }}</a>
            </li>
          </ul>
        </div>

        <div class="card p-5">
          <CommentThread
            :comments="article.comments || []"
            commentable-type="kb_article"
            :commentable-id="article.id"
          />
        </div>
      </article>

      <aside class="w-full shrink-0 space-y-4 lg:w-56">
        <div
          v-if="tocItems.length"
          class="card p-4 text-sm"
        >
          <p class="mb-2 text-xs font-semibold uppercase text-slate-400">
            Mục lục
          </p>
          <ul class="space-y-1">
            <li
              v-for="h in tocItems"
              :key="h.id"
              :class="h.level === 3 ? 'pl-3' : ''"
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
          class="card p-4"
        >
          <p class="mb-2 text-xs font-semibold uppercase text-slate-400">
            Bài liên quan
          </p>
          <ul class="space-y-2 text-sm">
            <li
              v-for="r in related"
              :key="r.id"
            >
              <Link
                :href="`/knowledge-base/articles/${r.slug}`"
                class="text-brand hover:underline"
              >
                {{ r.title }}
              </Link>
            </li>
          </ul>
        </div>
        <Link
          v-if="article.can?.update"
          :href="`/knowledge-base/articles/${article.slug}/edit`"
          class="btn-ghost inline-flex w-full justify-center text-sm"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
          Sửa bài
        </Link>
      </aside>
    </div>
  </AppLayout>
</template>
