<script setup>
/* eslint-disable vue/no-v-html -- article HTML from TipTap */
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import KbReadingProgress from '@/Components/KnowledgeBase/KbReadingProgress.vue';
import KbArticleHero from '@/Components/KnowledgeBase/KbArticleHero.vue';
import KbArticleCover from '@/Components/KnowledgeBase/KbArticleCover.vue';
import KbArticleToc from '@/Components/KnowledgeBase/KbArticleToc.vue';
import KbFloatingToolbar from '@/Components/KnowledgeBase/KbFloatingToolbar.vue';
import KbRelatedArticles from '@/Components/KnowledgeBase/KbRelatedArticles.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import { useKbScrollReveal } from '@/Components/KnowledgeBase/useKbScrollReveal.js';
import { useCommentThreadPoll } from '@/composables/useCommentThreadPoll';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    article: { type: Object, required: true },
    toc: { type: Array, default: () => [] },
    related: { type: Array, default: () => [] },
});

useCommentThreadPoll({
    active: computed(() => true),
    enabled: computed(() => true),
    subscribed: computed(() => false),
    reloadKeys: computed(() => ['article']),
});

const toast = useToast();
const favoriting = ref(false);
const markingRead = ref(false);
const isFavorite = ref(!!props.article.is_favorite);
const isRead = ref(!!props.article.is_read);
const contentRoot = ref(null);

useKbScrollReveal(contentRoot);

watch(
    () => props.article,
    (a) => {
        isFavorite.value = !!a.is_favorite;
        isRead.value = !!a.is_read;
    },
    { deep: true },
);

const tocItems = computed(() => (props.toc?.length ? props.toc : []));

const shareUrl = computed(() => {
    if (typeof window === 'undefined') return '';
    return window.location.href;
});

const headerTitle = computed(() => {
    const t = (props.article.title || '').trim();
    const labeled = t ? `Tiêu đề: ${t}` : 'Tiêu đề: —';
    return labeled.length > 72 ? `${labeled.slice(0, 69)}…` : labeled;
});

function toggleFavorite() {
    if (favoriting.value) return;
    favoriting.value = true;
    router.post(route('knowledge-base.articles.favorite', props.article.slug), {}, {
        preserveScroll: true,
        only: ['article'],
        onSuccess: ({ props: pageProps }) => {
            isFavorite.value = !!pageProps.article?.is_favorite;
            toast.success(isFavorite.value ? 'Đã thêm vào yêu thích.' : 'Đã bỏ yêu thích.');
        },
        onError: () => toast.error('Không cập nhật được yêu thích.'),
        onFinish: () => {
            favoriting.value = false;
        },
    });
}

function markRead() {
    if (markingRead.value || isRead.value) return;
    markingRead.value = true;
    router.post(route('knowledge-base.articles.read', props.article.slug), {}, {
        preserveScroll: true,
        only: ['article'],
        onSuccess: () => {
            isRead.value = true;
            toast.success('Đã đánh dấu đã đọc.');
        },
        onError: () => toast.error('Không đánh dấu được trạng thái đọc.'),
        onFinish: () => {
            markingRead.value = false;
        },
    });
}

async function shareFromHero() {
    const url = shareUrl.value;
    const title = props.article.title;
    try {
        if (typeof navigator !== 'undefined' && navigator.share) {
            await navigator.share({ title, url });
            return;
        }
    } catch {
        /* cancelled */
    }
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Đã sao chép liên kết.');
    } catch {
        toast.error('Không chia sẻ được.');
    }
}
</script>

<template>
  <Head :title="article.title" />
  <AppLayout>
    <KbReadingProgress />

    <template #header>
      <PageHeader
        :title="headerTitle"
        icon="knowledge"
        icon-color="brand"
        back-href="/knowledge-base"
      >
        <button
          v-if="!isRead"
          type="button"
          class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-2.5 text-xs sm:px-3 sm:text-sm"
          :disabled="markingRead"
          title="Đánh dấu đã đọc"
          @click="markRead"
        >
          <AppIcon
            name="check"
            :size="15"
          />
          <span class="hidden sm:inline">Đã đọc</span>
        </button>
      </PageHeader>
    </template>

    <article class="kb-article-show w-full min-w-0 pb-16">
      <section
        class="rounded-card border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/40"
        aria-label="Thông tin bài viết"
      >
        <KbArticleHero
          :article="article"
          :is-favorite="isFavorite"
          :favoriting="favoriting"
          @toggle-favorite="toggleFavorite"
          @share="shareFromHero"
        />
        <div class="border-t border-slate-100 px-4 pb-6 pt-2 dark:border-slate-800 sm:px-6 sm:pb-8">
          <KbArticleCover :article="article" />
        </div>
      </section>

      <div class="mt-8 grid w-full min-w-0 grid-cols-1 gap-6 lg:grid-cols-[minmax(0,240px)_minmax(0,1fr)_minmax(0,72px)] lg:gap-8">
        <div class="min-w-0">
          <KbArticleToc :items="tocItems" />
        </div>

        <div class="min-w-0">
          <div
            ref="contentRoot"
            class="kb-article-content rich-content prose prose-lg max-w-none prose-slate dark:prose-invert"
            v-html="article.content"
          />

          <div
            v-if="article.gallery_images?.length"
            class="mt-12"
          >
            <p class="mb-4 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
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
                  class="max-h-80 w-full rounded-xl object-cover ring-1 ring-slate-200/80 dark:ring-slate-700"
                  loading="lazy"
                >
                <figcaption
                  v-if="img.alt_text"
                  class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                >
                  {{ img.alt_text }}
                </figcaption>
              </figure>
            </div>
          </div>

          <ul
            v-if="article.attachments?.length"
            class="mt-10 space-y-2 text-sm"
          >
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
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

        <div class="min-w-0">
          <KbFloatingToolbar
            :is-favorite="isFavorite"
            :favoriting="favoriting"
            :share-url="shareUrl"
            :share-title="article.title"
            @toggle-favorite="toggleFavorite"
          />
        </div>
      </div>

      <div class="mt-16">
        <KbRelatedArticles :articles="related" />
      </div>

      <div class="mt-12 w-full min-w-0">
        <div class="rounded-card border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-6">
          <CommentThread
            :comments="article.comments || []"
            commentable-type="kb_article"
            :commentable-id="article.id"
            heading="Bình luận"
            empty-message="Chưa có bình luận. Hãy là người đầu tiên chia sẻ suy nghĩ."
            delete-dialog-title="Xoá bình luận"
            delete-button-title="Xoá bình luận"
            realtime-hint="Bình luận mới sẽ hiện ngay không cần tải lại trang"
            placeholder="Viết bình luận…"
          />
        </div>
      </div>
    </article>
  </AppLayout>
</template>

<style scoped>
.kb-article-content {
    font-size: 1.0625rem;
    line-height: 1.85;
}

.kb-article-content :deep(h2) {
    @apply mt-10 scroll-mt-28 font-display text-2xl font-semibold text-slate-900 dark:text-slate-50;
}

.kb-article-content :deep(h3) {
    @apply mt-8 scroll-mt-28 text-xl font-semibold text-slate-800 dark:text-slate-100;
}

.kb-article-content :deep(blockquote) {
    @apply my-6 border-l-4 border-brand/40 bg-brand/[0.03] py-2 pl-4 italic text-slate-600 dark:text-slate-300;
}

.kb-article-content :deep(pre) {
    @apply overflow-x-auto rounded-xl bg-slate-900 p-4 text-sm text-slate-100 dark:bg-slate-950;
}

.kb-article-content :deep(table) {
    @apply my-6 w-full overflow-hidden rounded-lg border border-slate-200 text-sm dark:border-slate-700;
}

.kb-article-content :deep(th),
.kb-article-content :deep(td) {
    @apply border border-slate-200 px-3 py-2 dark:border-slate-700;
}

.kb-article-show :deep(.kb-reveal) {
    opacity: 0;
    transform: translateY(12px);
    transition:
        opacity 0.5s ease,
        transform 0.5s ease;
    transition-delay: var(--kb-reveal-delay, 0ms);
}

.kb-article-show :deep(.kb-reveal--visible) {
    opacity: 1;
    transform: translateY(0);
}

@media (prefers-reduced-motion: reduce) {
    .kb-article-show :deep(.kb-reveal) {
        opacity: 1;
        transform: none;
        transition: none;
    }
}

@media print {
    .kb-article-show :deep(aside),
    .kb-article-show :deep(nav) {
        display: none !important;
    }
}
</style>
