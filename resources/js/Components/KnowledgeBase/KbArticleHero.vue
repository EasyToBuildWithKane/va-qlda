<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import HoverTooltip from '@/shared/ui/HoverTooltip.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
    isFavorite: { type: Boolean, default: false },
    favoriting: { type: Boolean, default: false },
    isRead: { type: Boolean, default: false },
    markingRead: { type: Boolean, default: false },
    commentCount: { type: Number, default: 0 },
});

defineEmits(['toggle-favorite', 'share', 'mark-read']);

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

    <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-3">
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

      <div class="flex flex-wrap items-center gap-2">
        <HoverTooltip
          v-if="!isRead"
          label="Ghi nhận bạn đã đọc xong — hữu ích cho theo dõi cá nhân"
          placement="top"
        >
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs sm:text-sm"
            :disabled="markingRead"
            @click="$emit('mark-read')"
          >
            <AppIcon
              name="check"
              :size="15"
            />
            Đánh dấu đã đọc
          </button>
        </HoverTooltip>
        <span
          v-else
          class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-emerald-200/80 bg-emerald-50/80 px-3 text-xs font-medium text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/30 dark:text-emerald-300 sm:text-sm"
        >
          <AppIcon
            name="check-circle"
            :size="15"
          />
          Đã đọc
        </span>
        <HoverTooltip
          :label="isFavorite ? 'Bỏ khỏi danh sách yêu thích' : 'Lưu bài để xem lại sau trên blog'"
          placement="top"
        >
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200/90 bg-white px-3 text-xs font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm"
            :class="isFavorite ? 'border-amber-300/80 text-amber-700 dark:border-amber-600/50 dark:text-amber-400' : ''"
            :disabled="favoriting"
            @click="$emit('toggle-favorite')"
          >
            <AppIcon
              name="star"
              :size="15"
            />
            {{ isFavorite ? 'Đã lưu' : 'Lưu bài' }}
          </button>
        </HoverTooltip>
        <HoverTooltip
          label="Chia sẻ hoặc sao chép liên kết tới đúng bài viết"
          placement="top"
        >
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200/90 bg-white px-3 text-xs font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm"
            @click="$emit('share')"
          >
            <AppIcon
              name="link"
              :size="15"
            />
            Chia sẻ
          </button>
        </HoverTooltip>
        <HoverTooltip
          label="Cuộn xuống phần thảo luận"
          placement="top"
        >
          <a
            href="#comments"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200/90 bg-white px-3 text-xs font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm"
          >
            <AppIcon
              name="comment"
              :size="15"
            />
            Bình luận
            <span
              v-if="commentCount > 0"
              class="tabular-nums text-slate-400"
            >({{ commentCount }})</span>
          </a>
        </HoverTooltip>
      </div>
    </div>

    <div
      v-if="article.tags?.length"
      class="mt-5 flex flex-wrap gap-2"
    >
      <Link
        v-for="t in article.tags"
        :key="t.id"
        :href="`/knowledge-base/blog?tag=${encodeURIComponent(t.slug)}`"
        class="inline-flex items-center rounded-md border border-slate-200/90 bg-slate-50/80 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-brand/25 hover:bg-brand/[0.04] hover:text-brand dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300"
      >
        #{{ t.name }}
      </Link>
    </div>
  </header>
</template>
