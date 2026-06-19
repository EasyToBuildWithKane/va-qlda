<script setup>
/* eslint-disable vue/no-v-html -- article HTML from TipTap */
import {
    computed, onMounted, ref, watch,
} from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import KbReadingProgress from '@/modules/knowledge-base/components/KbReadingProgress.vue';
import KbArticleHero from '@/modules/knowledge-base/components/KbArticleHero.vue';
import KbArticleCover from '@/modules/knowledge-base/components/KbArticleCover.vue';
import KbArticleToc from '@/modules/knowledge-base/components/KbArticleToc.vue';
import KbFloatingToolbar from '@/modules/knowledge-base/components/KbFloatingToolbar.vue';
import KbRelatedArticles from '@/modules/knowledge-base/components/KbRelatedArticles.vue';
import KbMoreArticles from '@/modules/knowledge-base/components/KbMoreArticles.vue';
import KbArticleCommentsSection from '@/modules/knowledge-base/components/KbArticleCommentsSection.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    article: { type: Object, required: true },
    toc: { type: Array, default: () => [] },
    related: { type: Array, default: () => [] },
    otherArticles: { type: Array, default: () => [] },
});

const toast = useToast();
const favoriting = ref(false);
const markingRead = ref(false);
const isFavorite = ref(!!props.article.is_favorite);
const isRead = ref(!!props.article.is_read);

watch(
    () => props.article,
    (a) => {
        isFavorite.value = !!a.is_favorite;
        isRead.value = !!a.is_read;
    },
    { deep: true },
);

const tocItems = computed(() => (props.toc?.length ? props.toc : []));

const commentCount = computed(() => (props.article.comments || []).length);

const shareUrl = computed(() => {
    if (typeof window === 'undefined') return '';
    return window.location.href;
});

const pageHeaderTitle = computed(() => {
    const t = (props.article.title || '').trim();
    if (!t) return 'Bài viết';
    return t.length > 56 ? `${t.slice(0, 53)}…` : t;
});

function scrollToHash() {
    if (typeof window === 'undefined') return;
    const hash = window.location.hash?.replace(/^#/, '');
    if (!hash) return;
    requestAnimationFrame(() => {
        const el = document.getElementById(hash);
        el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

onMounted(() => {
    scrollToHash();
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

</script>

<template>
  <Head :title="article.title" />
  <AppLayout :flush="true">
    <KbReadingProgress />

    <template #header>
      <PageHeader
        :title="pageHeaderTitle"
        icon="knowledge"
        icon-color="brand"
      />
    </template>

    <div class="kb-article-show relative min-h-0 w-full min-w-0 flex-1 overflow-y-auto px-3 pb-16 sm:px-5 lg:px-6">
      <KbFloatingToolbar
        :is-favorite="isFavorite"
        :favoriting="favoriting"
        :is-read="isRead"
        :marking-read="markingRead"
        :share-url="shareUrl"
        :share-title="article.title"
        @toggle-favorite="toggleFavorite"
        @mark-read="markRead"
      />

      <div class="mt-4 sm:mt-5">
        <article class="min-w-0 w-full">
          <div class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40 sm:p-5 lg:p-6">
            <KbArticleHero
              :article="article"
              :comment-count="commentCount"
            />

            <div
              v-if="article.excerpt?.trim()"
              class="mt-5 rounded-lg border-l-4 border-brand/40 bg-gradient-to-r from-brand/[0.04] to-slate-50/50 px-3.5 py-2.5 text-[0.9375rem] leading-relaxed text-slate-600 dark:from-brand/10 dark:to-slate-900/30 dark:text-slate-300"
            >
              <!-- eslint-disable-next-line vue/no-v-html -->
              <div
                class="prose prose-sm max-w-none dark:prose-invert"
                v-html="article.excerpt"
              />
            </div>

            <div class="mt-5">
              <KbArticleCover :article="article" />
            </div>

            <KbArticleToc
              :items="tocItems"
              variant="plain"
              display="mobile"
            />

            <div
              class="kb-article-content rich-content prose prose-lg max-w-none prose-slate dark:prose-invert"
              v-html="article.content"
            />

            <section
              v-if="article.gallery_images?.length"
              class="mt-12 border-t border-slate-100 pt-10 dark:border-slate-800"
              aria-label="Thư viện ảnh"
            >
              <div class="mb-4 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-slate-50">
                  Thư viện ảnh
                </h2>
                <FieldTooltip text="Ảnh minh họa bổ sung ngoài nội dung chính — bấm để xem kích thước đầy đủ trong tab mới nếu được liên kết." />
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <figure
                  v-for="img in article.gallery_images"
                  :key="img.id"
                >
                  <img
                    :src="img.url"
                    :alt="img.alt_text || img.original_name"
                    class="w-full rounded-lg object-cover ring-1 ring-slate-200/80 dark:ring-slate-700"
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
            </section>

            <section
              v-if="article.attachments?.length"
              class="mt-10 border-t border-slate-100 pt-8 dark:border-slate-800"
              aria-label="Tệp đính kèm"
            >
              <div class="mb-3 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-slate-50">
                  Tệp đính kèm
                </h2>
                <FieldTooltip text="Tài liệu tải về (PDF, Word, …). Mở trong tab mới để lưu hoặc in." />
              </div>
              <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200/90 dark:divide-slate-800 dark:border-slate-700">
                <li
                  v-for="att in article.attachments"
                  :key="att.id"
                >
                  <a
                    :href="att.url"
                    class="flex items-center gap-2 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50 hover:text-brand dark:text-slate-300 dark:hover:bg-slate-900/50"
                    target="_blank"
                    rel="noopener"
                  >
                    <AppIcon
                      name="documents"
                      :size="16"
                      class="shrink-0 text-slate-400"
                    />
                    <span class="min-w-0 truncate">{{ att.original_name }}</span>
                  </a>
                </li>
              </ul>
            </section>
          </div>
        </article>
      </div>

      <section
        v-if="related?.length"
        class="mt-10 border-t border-slate-200/80 pt-8 dark:border-slate-800 sm:mt-12 sm:pt-10"
        aria-label="Bài viết cùng chuyên mục"
      >
        <KbRelatedArticles :articles="related" />
      </section>

      <div class="mt-10 w-full min-w-0 sm:mt-12">
        <KbArticleCommentsSection
          :comments="article.comments || []"
          :article-id="article.id"
          :article-title="article.title"
        />
      </div>

      <section
        v-if="otherArticles?.length"
        class="mt-10 border-t border-slate-200/80 pt-8 dark:border-slate-800 sm:mt-12 sm:pt-10"
        aria-label="Các bài viết khác"
      >
        <KbMoreArticles
          :articles="otherArticles"
          :current-slug="article.slug"
        />
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.kb-article-content {
    font-size: 1.0625rem;
    line-height: 1.85;
    color: rgb(51 65 85);
}

.dark .kb-article-content {
    color: rgb(203 213 225);
}

.kb-article-content :deep(h2) {
    @apply mt-12 scroll-mt-28 border-b border-slate-100 pb-2.5 font-display text-xl font-bold text-slate-900 dark:border-slate-800 dark:text-slate-50 sm:text-2xl;
}

.kb-article-content :deep(h3) {
    @apply mt-8 scroll-mt-28 text-lg font-semibold text-slate-800 dark:text-slate-100;
}

.kb-article-content :deep(p) {
    @apply my-4;
}

.kb-article-content :deep(a) {
    @apply text-brand underline decoration-brand/30 underline-offset-2 transition hover:decoration-brand;
}

.kb-article-content :deep(img) {
    @apply my-6 rounded-lg ring-1 ring-slate-200/60 dark:ring-slate-700;
}

.kb-article-content :deep(blockquote) {
    @apply my-6 border-l-4 border-brand/40 bg-slate-50 py-3 pl-4 pr-2 italic text-slate-600 dark:bg-slate-900/40 dark:text-slate-300;
}

.kb-article-content :deep(pre) {
    @apply my-6 overflow-x-auto rounded-lg bg-slate-900 p-4 text-[0.875rem] leading-relaxed text-slate-100 dark:bg-slate-950;
}

.kb-article-content :deep(code:not(pre code)) {
    @apply rounded bg-slate-100 px-1.5 py-0.5 text-[0.9em] text-brand dark:bg-slate-800;
}

.kb-article-content :deep(ul),
.kb-article-content :deep(ol) {
    @apply my-4 pl-6;
}

.kb-article-content :deep(li) {
    @apply my-1.5;
}

.kb-article-content :deep(table) {
    @apply my-6 w-full overflow-hidden rounded-lg border border-slate-200 text-sm dark:border-slate-700;
}

.kb-article-content :deep(th),
.kb-article-content :deep(td) {
    @apply border border-slate-200 px-3 py-2 dark:border-slate-700;
}

.kb-article-content :deep(th) {
    @apply bg-slate-50 font-semibold dark:bg-slate-900/80;
}

@media print {
    .kb-article-show :deep(.kb-article-toc--mobile) {
        display: none !important;
    }

    .kb-article-show :deep(.kb-article-sidebar) {
        display: none !important;
    }
}
</style>
