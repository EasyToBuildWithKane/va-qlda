<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import RichContentBody from '@/shared/ui/RichContentBody.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
    isFavorite: { type: Boolean, default: false },
    favoriting: { type: Boolean, default: false },
});

defineEmits(['toggle-favorite', 'share']);

const authorName = computed(() => {
    const a = props.article.author;
    if (!a) return '';
    return (a.full_name || a.name || '').trim();
});

const readingMinutes = computed(() => props.article.reading_time ?? 1);
</script>

<template>
  <header class="w-full px-4 pb-8 pt-4 text-center sm:px-6 sm:pb-10 sm:pt-6">
    <div class="flex flex-wrap items-center justify-center gap-2">
      <Link
        v-if="article.category"
        :href="`/knowledge-base/blog?category_id=${article.category.id}`"
        class="inline-flex items-center rounded-full border border-brand/15 bg-brand/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-brand transition hover:border-brand/30 hover:bg-brand/10 dark:border-brand/25 dark:bg-brand/10"
      >
        {{ article.category.name }}
      </Link>
      <span
        v-if="article.status?.value && article.status.value !== 'published'"
        class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
      >
        {{ article.status.label }}
      </span>
    </div>

    <h1
      class="mt-6 font-display text-3xl font-semibold leading-[1.15] tracking-tight text-slate-900 dark:text-slate-50 sm:text-4xl lg:text-[2.75rem] lg:leading-[1.12]"
    >
      {{ article.title }}
    </h1>

    <div
      v-if="article.excerpt?.trim()"
      class="mx-auto mt-6 max-w-2xl text-left sm:text-center"
    >
      <RichContentBody
        :content="article.excerpt"
        empty-text=""
        html-class="prose prose-base max-w-none text-slate-600 dark:prose-invert dark:text-slate-400"
        plain-class="text-base leading-relaxed text-slate-600 dark:text-slate-400"
      />
    </div>

    <div
      v-if="article.tags?.length"
      class="mt-6 flex flex-wrap justify-center gap-2"
    >
      <span
        v-for="t in article.tags"
        :key="t.id"
        class="inline-flex items-center rounded-full bg-slate-100/80 px-2.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800/80 dark:text-slate-300"
      >
        #{{ t.name }}
      </span>
    </div>

    <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-6">
      <div
        v-if="authorName"
        class="flex items-center gap-3"
      >
        <Avatar
          :name="authorName"
          :src="article.author?.avatar_path"
          :size="44"
        />
        <div class="text-left">
          <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ authorName }}
          </p>
          <p
            v-if="article.author?.role_title"
            class="text-xs text-slate-500 dark:text-slate-400"
          >
            {{ article.author.role_title }}
          </p>
        </div>
      </div>

      <div class="hidden h-8 w-px bg-slate-200 dark:bg-slate-700 sm:block" />

      <ul class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
        <li
          v-if="article.published_at"
          class="inline-flex items-center gap-1.5"
        >
          <AppIcon
            name="calendar"
            :size="14"
            class="text-slate-400"
          />
          {{ date(article.published_at) }}
        </li>
        <li class="inline-flex items-center gap-1.5">
          <AppIcon
            name="clock"
            :size="14"
            class="text-slate-400"
          />
          {{ readingMinutes }} phút đọc
        </li>
        <li class="inline-flex items-center gap-1.5">
          <AppIcon
            name="eye"
            :size="14"
            class="text-slate-400"
          />
          {{ article.view_count }} lượt xem
        </li>
      </ul>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
      <button
        type="button"
        class="inline-flex h-10 items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-4 text-sm font-medium text-slate-700 shadow-sm backdrop-blur-sm transition hover:border-brand/25 hover:text-brand dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200"
        :class="isFavorite ? 'border-amber-300/80 text-amber-700 dark:border-amber-600/50 dark:text-amber-400' : ''"
        :disabled="favoriting"
        @click="$emit('toggle-favorite')"
      >
        <AppIcon
          name="star"
          :size="16"
        />
        {{ isFavorite ? 'Đã lưu' : 'Lưu bài' }}
      </button>
      <button
        type="button"
        class="inline-flex h-10 items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-4 text-sm font-medium text-slate-700 shadow-sm backdrop-blur-sm transition hover:border-brand/25 hover:text-brand dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200"
        @click="$emit('share')"
      >
        <AppIcon
          name="link"
          :size="16"
        />
        Chia sẻ
      </button>
    </div>
  </header>
</template>
