<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
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

function applyTag(slug) {
    emit('filter-tag', slug);
}

const hasActiveFilter = () =>
    Boolean(props.filters.category_id || props.filters.tag || props.filters.q);
</script>

<template>
  <div class="kb-blog-sidebar space-y-3 xl:sticky xl:top-5">
    <section
      class="kb-blog-panel overflow-hidden"
      aria-label="Tìm kiếm blog"
    >
      <div class="kb-blog-panel__head flex items-center justify-between gap-2">
        <p class="kb-blog-panel__eyebrow">
          Tìm kiếm
        </p>
        <Link
          href="/knowledge-base"
          class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-400 transition hover:text-brand"
          title="Chế độ thư viện"
        >
          <AppIcon
            name="knowledge"
            :size="12"
          />
          Thư viện
        </Link>
      </div>
      <div class="relative px-4 pb-4">
        <AppIcon
          name="search"
          :size="16"
          class="pointer-events-none absolute left-7 top-1/2 -translate-y-1/2 text-slate-400"
        />
        <input
          :value="searchQuery"
          type="search"
          class="input h-10 w-full border-slate-200/80 bg-slate-50/50 pl-9 text-sm transition focus:bg-white"
          placeholder="Từ khoá…"
          aria-label="Tìm kiếm bài viết"
          @input="emit('update:searchQuery', $event.target.value)"
        >
        <button
          v-if="hasActiveFilter()"
          type="button"
          class="mt-2 text-xs font-medium text-brand hover:underline"
          @click="emit('clear-filters')"
        >
          Xóa bộ lọc
        </button>
      </div>
    </section>

    <section
      v-if="sidebar.categories?.length"
      class="kb-blog-panel"
      aria-label="Chuyên mục"
    >
      <p class="kb-blog-panel__eyebrow kb-blog-panel__head">
        Chuyên mục
      </p>
      <ul class="space-y-0.5 px-2 pb-3 text-sm">
        <li
          v-for="cat in sidebar.categories"
          :key="cat.id"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-2 text-left text-slate-600 transition hover:bg-slate-50 hover:text-brand"
            :class="String(filters.category_id) === String(cat.id)
              ? 'bg-brand/[0.06] font-medium text-brand shadow-[inset_2px_0_0_0_theme(colors.brand.DEFAULT)]'
              : ''"
            @click="applyCategory(cat.id)"
          >
            <span class="truncate">{{ cat.name }}</span>
            <span class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 tabular-nums text-[10px] text-slate-500">
              {{ cat.articles_count }}
            </span>
          </button>
        </li>
      </ul>
    </section>

    <section
      v-if="sidebar.recentPosts?.length"
      class="kb-blog-panel"
      aria-label="Bài viết mới"
    >
      <p class="kb-blog-panel__eyebrow kb-blog-panel__head">
        Bài viết mới
      </p>
      <ul class="space-y-1 px-2 pb-3">
        <li
          v-for="post in sidebar.recentPosts"
          :key="post.id"
        >
          <Link
            :href="`/knowledge-base/articles/${post.slug}`"
            class="group flex gap-2.5 rounded-lg p-2 transition hover:bg-slate-50"
          >
            <div
              class="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200/60"
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
            <div class="min-w-0 flex-1">
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
    </section>

    <section
      v-if="sidebar.tags?.length"
      class="kb-blog-panel xl:hidden"
      aria-label="Thẻ"
    >
      <p class="kb-blog-panel__eyebrow kb-blog-panel__head">
        Thẻ
      </p>
      <div class="flex flex-wrap gap-1.5 px-4 pb-4">
        <button
          v-for="tag in sidebar.tags"
          :key="tag.id"
          type="button"
          class="rounded-full border border-slate-200/80 bg-white px-2.5 py-0.5 text-[11px] text-slate-600 shadow-sm transition hover:border-brand/25 hover:text-brand"
          :class="filters.tag === tag.slug ? 'border-brand/35 bg-brand/[0.06] text-brand' : ''"
          @click="applyTag(tag.slug)"
        >
          {{ tag.name }}
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.kb-blog-panel {
  @apply rounded-card border border-slate-200/60 bg-white/80 shadow-sm backdrop-blur-sm;
}

.kb-blog-panel__head {
  @apply px-4 pt-3.5;
}

.kb-blog-panel__eyebrow {
  @apply text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400;
}
</style>
