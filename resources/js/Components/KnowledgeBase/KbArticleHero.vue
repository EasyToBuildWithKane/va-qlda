<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
    commentCount: { type: Number, default: 0 },
});

const authorName = computed(() => {
    const a = props.article.author;
    if (!a) return '';
    return (a.full_name || a.name || '').trim();
});

const readingMinutes = computed(() => props.article.reading_time ?? 1);
</script>

<template>
  <header class="kb-article-hero border-b border-slate-200/80 pb-8 dark:border-slate-800">
    <Link
      v-if="article.category"
      :href="`/knowledge-base/blog?category_id=${article.category.id}`"
      class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-brand/[0.07] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-brand transition hover:bg-brand/10"
    >
      <AppIcon
        name="portfolio"
        :size="12"
      />
      {{ article.category.name }}
    </Link>

    <h1
      class="font-display text-[1.75rem] font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-50 sm:text-[2rem] lg:text-[2.25rem]"
    >
      {{ article.title }}
    </h1>

    <div class="mt-5 flex min-w-0 flex-wrap items-center gap-x-4 gap-y-3">
      <div
        v-if="authorName"
        class="flex items-center gap-2.5"
      >
        <Avatar
          :name="authorName"
          :src="article.author?.avatar_path"
          :size="40"
        />
        <div class="min-w-0 text-left">
          <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ authorName }}
          </p>
          <p
            v-if="article.author?.role_title"
            class="truncate text-xs text-slate-500 dark:text-slate-400"
          >
            {{ article.author.role_title }}
          </p>
        </div>
      </div>

      <ul class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400 sm:border-l sm:border-slate-200 sm:pl-4 dark:sm:border-slate-700">
        <li
          v-if="article.published_at"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            name="calendar"
            :size="13"
          />
          {{ date(article.published_at) }}
        </li>
        <li class="inline-flex items-center gap-1">
          <AppIcon
            name="clock"
            :size="13"
          />
          {{ readingMinutes }} phút đọc
        </li>
        <li class="inline-flex items-center gap-1">
          <AppIcon
            name="eye"
            :size="13"
          />
          {{ article.view_count ?? 0 }} lượt xem
        </li>
        <li
          v-if="commentCount > 0"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            name="comment"
            :size="13"
          />
          {{ commentCount }} bình luận
        </li>
      </ul>
    </div>
  </header>
</template>
