<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    sidebar: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    searchQuery: { type: String, default: '' },
});

const emit = defineEmits(['update:searchQuery']);

function filterLink(params) {
    return route('knowledge-base.blog', {
        ...params,
        q: props.filters.q || undefined,
        per_page: props.filters.per_page || undefined,
    });
}

function applyTag(slug) {
    router.get(route('knowledge-base.blog'), {
        tag: slug,
        category_id: props.filters.category_id || undefined,
        q: props.filters.q || undefined,
    }, { preserveScroll: true, preserveState: true });
}

function clearFilters() {
    router.get(route('knowledge-base.blog'), {}, { preserveScroll: true });
}

const hasActiveFilter = () =>
    Boolean(props.filters.category_id || props.filters.tag || props.filters.q);
</script>

<template>
  <div class="space-y-4">
    <section
      class="overflow-hidden rounded-card border border-slate-800/90 bg-slate-900 text-slate-100 shadow-sm"
      aria-label="Giới thiệu blog tri thức"
    >
      <div class="border-b border-white/10 px-4 py-3">
        <p class="font-display text-sm font-semibold tracking-wide">
          Blog tri thức
        </p>
      </div>
      <div class="space-y-3 px-4 py-4 text-sm leading-relaxed text-slate-300">
        <p>
          Kinh nghiệm thực tế, HOWTO và góc nhìn nội bộ VAschools — đọc như một tạp chí kỹ thuật.
        </p>
        <Link
          href="/knowledge-base"
          class="inline-flex items-center gap-1 text-xs font-medium text-white/80 hover:text-white"
        >
          <AppIcon
            name="knowledge"
            :size="14"
          />
          Chế độ thư viện
        </Link>
      </div>
    </section>

    <section
      class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
      aria-label="Tìm kiếm blog"
    >
      <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
        Tìm kiếm
      </p>
      <div class="relative">
        <AppIcon
          name="search"
          :size="16"
          class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
        />
        <input
          :value="searchQuery"
          type="search"
          class="input h-10 w-full pl-9 text-sm"
          placeholder="Từ khoá…"
          aria-label="Tìm kiếm bài viết"
          @input="emit('update:searchQuery', $event.target.value)"
        >
      </div>
      <button
        v-if="hasActiveFilter()"
        type="button"
        class="mt-2 text-xs text-brand hover:underline"
        @click="clearFilters"
      >
        Xóa bộ lọc
      </button>
    </section>

    <section
      v-if="sidebar.categories?.length"
      class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
      aria-label="Chuyên mục"
    >
      <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
        Chuyên mục
      </p>
      <ul class="space-y-1 text-sm">
        <li
          v-for="cat in sidebar.categories"
          :key="cat.id"
        >
          <Link
            :href="filterLink({ category_id: cat.id, tag: undefined })"
            class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-brand"
            :class="String(filters.category_id) === String(cat.id) ? 'bg-brand/5 font-medium text-brand' : ''"
          >
            <span class="truncate">{{ cat.name }}</span>
            <span class="shrink-0 tabular-nums text-xs text-slate-400">{{ cat.articles_count }}</span>
          </Link>
        </li>
      </ul>
    </section>

    <section
      v-if="sidebar.recentPosts?.length"
      class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
      aria-label="Bài viết mới"
    >
      <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
        Bài viết mới
      </p>
      <ul class="space-y-3">
        <li
          v-for="post in sidebar.recentPosts"
          :key="post.id"
        >
          <Link
            :href="`/knowledge-base/articles/${post.slug}`"
            class="group flex gap-2.5"
          >
            <div
              class="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200/80"
            >
              <img
                v-if="post.cover_url"
                :src="post.cover_url"
                alt=""
                class="h-full w-full object-cover transition group-hover:scale-105"
                loading="lazy"
              >
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/15 to-slate-100 text-brand/40"
              >
                <AppIcon
                  name="knowledge"
                  :size="18"
                />
              </div>
            </div>
            <div class="min-w-0 flex-1">
              <p class="line-clamp-2 text-xs font-medium leading-snug text-slate-700 group-hover:text-brand">
                {{ post.title }}
              </p>
              <p
                v-if="post.published_at"
                class="mt-0.5 text-[10px] text-slate-400"
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
      class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm xl:hidden"
      aria-label="Thẻ"
    >
      <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
        Thẻ
      </p>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="tag in sidebar.tags"
          :key="tag.id"
          type="button"
          class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-600 transition hover:border-brand/30 hover:text-brand"
          :class="filters.tag === tag.slug ? 'border-brand/40 bg-brand/5 text-brand' : ''"
          @click="applyTag(tag.slug)"
        >
          {{ tag.name }}
        </button>
      </div>
    </section>
  </div>
</template>
