<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import KbArticleCardsSwiper from '@/modules/knowledge-base/components/KbArticleCardsSwiper.vue';
import KbBlogPostCard from '@/modules/knowledge-base/components/KbBlogPostCard.vue';

const props = defineProps({
    articles: { type: Array, default: () => [] },
    currentSlug: { type: String, default: '' },
});

const items = computed(() => props.articles.filter((a) => a.slug !== props.currentSlug));

const totalLabel = computed(() => {
    const n = items.value.length;
    if (n === 0) return '';
    return n === 1 ? '1 bài viết' : `${n} bài viết`;
});
</script>

<template>
  <section
    v-if="items.length"
    class="w-full min-w-0"
    aria-label="Các bài viết khác"
  >
    <div class="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:items-end sm:justify-between sm:pr-24">
      <div class="min-w-0 flex-1">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Khám phá thêm
        </p>
        <h2 class="font-display text-xl font-semibold text-slate-900 dark:text-slate-50">
          Tất cả bài viết khác
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ totalLabel }} trên Knowledge Base — vuốt ngang để xem thêm.
        </p>
      </div>
      <Link
        href="/knowledge-base/blog"
        class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-sm"
      >
        <AppIcon
          name="knowledge"
          :size="15"
        />
        Xem trang blog
      </Link>
    </div>

    <KbArticleCardsSwiper
      :articles="items"
      aria-label="Các bài viết khác trên Knowledge Base"
    >
      <template #slide="{ article }">
        <KbBlogPostCard
          :article="article"
          variant="compact"
        />
      </template>
    </KbArticleCardsSwiper>
  </section>
</template>
