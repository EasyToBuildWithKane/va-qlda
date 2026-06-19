<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import KbBlogPanel from '@/modules/knowledge-base/components/KbBlogPanel.vue';
import KbBlogTagSection from '@/modules/knowledge-base/components/KbBlogTagSection.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    sidebar: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    searchQuery: { type: String, default: '' },
});

const emit = defineEmits([
    'update:searchQuery',
    'filter-category',
    'filter-tag',
    'clear-filters',
]);

function applyCategory(categoryId) {
    emit('filter-category', categoryId);
}

const hasActiveFilter = () =>
    Boolean(props.filters.category_id || props.filters.tag || props.filters.q);
</script>

<template>
  <div class="kb-blog-sidebar flex flex-col gap-3 sm:gap-3.5 xl:sticky xl:top-5">
    <KbBlogPanel
      aria-label="Tìm kiếm blog"
      title="Tìm kiếm"
    >
      <template #head-actions>
        <Link
          href="/knowledge-base"
          class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-medium text-slate-400 transition hover:bg-slate-50 hover:text-brand"
          title="Chế độ thư viện"
        >
          <AppIcon
            name="knowledge"
            :size="12"
          />
          Thư viện
        </Link>
      </template>

      <div class="flex flex-col gap-2">
        <label
          class="flex h-10 w-full items-center gap-2.5 rounded-lg border border-slate-200/80 bg-slate-50/60 px-3 transition focus-within:border-brand/25 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand/10"
        >
          <AppIcon
            name="search"
            :size="16"
            class="shrink-0 text-slate-400"
          />
          <input
            :value="searchQuery"
            type="search"
            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0"
            placeholder="Từ khoá…"
            aria-label="Tìm kiếm bài viết"
            @input="emit('update:searchQuery', $event.target.value)"
          >
        </label>
        <button
          v-if="hasActiveFilter()"
          type="button"
          class="self-start text-xs font-medium text-brand hover:underline"
          @click="emit('clear-filters')"
        >
          Xóa bộ lọc
        </button>
      </div>
    </KbBlogPanel>

    <KbBlogPanel
      v-if="sidebar.categories?.length"
      aria-label="Chuyên mục"
      title="Chuyên mục"
      flush-body
    >
      <ul class="divide-y divide-slate-100/90 text-sm">
        <li
          v-for="cat in sidebar.categories"
          :key="cat.id"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-slate-600 transition hover:bg-slate-50 hover:text-brand sm:px-4"
            :class="String(filters.category_id) === String(cat.id)
              ? 'bg-brand/[0.06] font-medium text-brand'
              : ''"
            :aria-pressed="String(filters.category_id) === String(cat.id)"
            @click="applyCategory(cat.id)"
          >
            <span class="min-w-0 truncate">{{ cat.name }}</span>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 tabular-nums text-[10px] font-semibold"
              :class="String(filters.category_id) === String(cat.id)
                ? 'bg-brand/10 text-brand'
                : 'bg-slate-100 text-slate-500'"
            >
              {{ cat.articles_count }}
            </span>
          </button>
        </li>
      </ul>
    </KbBlogPanel>

    <KbBlogPanel
      v-if="sidebar.recentPosts?.length"
      aria-label="Bài viết mới"
      title="Bài viết mới"
      flush-body
    >
      <ul class="divide-y divide-slate-100/90">
        <li
          v-for="post in sidebar.recentPosts"
          :key="post.id"
        >
          <Link
            :href="`/knowledge-base/articles/${post.slug}`"
            class="group flex gap-2.5 px-3.5 py-2.5 transition hover:bg-slate-50 sm:px-4"
          >
            <div
              class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200/60 sm:h-11 sm:w-11"
            >
              <img
                v-if="post.cover_url"
                :src="post.cover_url"
                alt=""
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                loading="lazy"
              >
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/10 to-slate-100 text-brand/35"
              >
                <AppIcon
                  name="knowledge"
                  :size="16"
                />
              </div>
            </div>
            <div class="min-w-0 flex-1 py-0.5">
              <p class="line-clamp-2 text-xs font-medium leading-snug text-slate-700 group-hover:text-brand">
                {{ post.title }}
              </p>
              <p
                v-if="post.published_at"
                class="mt-0.5 text-[10px] tabular-nums text-slate-400"
              >
                {{ date(post.published_at) }}
              </p>
            </div>
          </Link>
        </li>
      </ul>
    </KbBlogPanel>

    <div
      v-if="sidebar.tags?.length"
      class="xl:hidden"
    >
      <KbBlogTagSection
        :tags="sidebar.tags"
        :active-slug="filters.tag"
        layout="chips"
        @filter-tag="(slug) => emit('filter-tag', slug)"
      />
    </div>
  </div>
</template>
