<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import KbArticleCardsSwiper from '@/modules/knowledge-base/components/KbArticleCardsSwiper.vue';
import { richContentPlainText } from '@/shared/utils/richContent';
import { date } from '@/composables/useFormat';
import { kbCategoryGradientClass, kbCoverImageUrl } from '@/modules/knowledge-base/composables/useKbCategoryGradient.js';

defineProps({
    articles: { type: Array, default: () => [] },
});

function excerpt(article) {
    const raw = article.excerpt?.trim() || richContentPlainText(article.content);
    const plain = richContentPlainText(raw);
    if (!plain) return '';
    return plain.length > 120 ? `${plain.slice(0, 119)}…` : plain;
}

function gradientSeed(article) {
    return article.category?.slug || article.category?.name || article.slug || '';
}

function cover(article) {
    return kbCoverImageUrl(article);
}
</script>

<template>
  <section
    v-if="articles.length"
    class="w-full min-w-0"
    aria-label="Bài viết liên quan"
  >
    <div class="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:items-end sm:justify-between sm:pr-24">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Tiếp theo
        </p>
        <h2 class="font-display text-xl font-semibold text-slate-900 dark:text-slate-50">
          Cùng chuyên mục
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Gợi ý nhanh trong danh mục «{{ articles[0]?.category?.name || 'này' }}» — vuốt ngang hoặc dùng mũi tên.
        </p>
      </div>
    </div>

    <KbArticleCardsSwiper
      :articles="articles"
      aria-label="Bài viết cùng chuyên mục"
    >
      <template #slide="{ article: item }">
        <Link
          :href="`/knowledge-base/articles/${item.slug}`"
          class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm transition duration-300 hover:border-brand/20 hover:shadow-[0_12px_40px_-12px_rgba(154,0,54,0.12)] dark:border-slate-700 dark:bg-slate-900/50"
        >
          <div class="relative aspect-[16/10] overflow-hidden bg-slate-100 dark:bg-slate-800">
            <img
              v-if="cover(item)"
              :src="cover(item)"
              alt=""
              class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
              loading="lazy"
            >
            <div
              v-else
              class="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br"
              :class="kbCategoryGradientClass(gradientSeed(item))"
            >
              <AppIcon
                name="knowledge"
                :size="36"
                class="text-brand/30"
              />
            </div>
          </div>
          <div class="flex flex-1 flex-col p-3 sm:p-3.5">
            <span
              v-if="item.category?.name"
              class="text-[11px] font-semibold uppercase tracking-wide text-brand/80"
            >
              {{ item.category.name }}
            </span>
            <h3 class="mt-2 font-display text-base font-semibold leading-snug text-slate-900 group-hover:text-brand dark:text-slate-100">
              {{ item.title }}
            </h3>
            <p
              v-if="excerpt(item)"
              class="mt-2 line-clamp-2 flex-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400"
            >
              {{ excerpt(item) }}
            </p>
            <p class="mt-3 flex flex-wrap gap-x-3 text-xs text-slate-400">
              <span v-if="item.reading_time">{{ item.reading_time }} phút đọc</span>
              <span v-if="item.published_at">{{ date(item.published_at) }}</span>
            </p>
          </div>
        </Link>
      </template>
    </KbArticleCardsSwiper>
  </section>
</template>
