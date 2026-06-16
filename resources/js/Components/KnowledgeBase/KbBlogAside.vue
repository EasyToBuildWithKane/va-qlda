<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    sidebar: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['filter-tag']);

const tagSizeClass = (count, max) => {
    if (max <= 0) return 'text-[11px]';
    const ratio = count / max;
    if (ratio >= 0.75) return 'text-sm font-semibold';
    if (ratio >= 0.4) return 'text-xs font-medium';
    return 'text-[11px]';
};

const maxTagCount = computed(() => {
    const counts = (props.sidebar.tags ?? []).map((t) => t.articles_count ?? 0);
    return counts.length ? Math.max(...counts) : 0;
});
</script>

<template>
  <div class="kb-blog-aside space-y-3 xl:sticky xl:top-5">
    <section
      v-if="sidebar.popularPosts?.length"
      class="kb-blog-panel"
      aria-label="Bài xem nhiều"
    >
      <p class="kb-blog-panel__eyebrow px-4 pt-3.5">
        Xem nhiều
      </p>
      <ul class="space-y-0.5 px-2 pb-3">
        <li
          v-for="(post, idx) in sidebar.popularPosts"
          :key="post.id"
        >
          <Link
            :href="`/knowledge-base/articles/${post.slug}`"
            class="group flex gap-2.5 rounded-lg p-2 transition hover:bg-slate-50"
          >
            <span
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 font-display text-xs font-semibold tabular-nums text-slate-400 group-hover:bg-brand/[0.08] group-hover:text-brand"
              aria-hidden="true"
            >
              {{ idx + 1 }}
            </span>
            <div
              class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200/60"
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
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/10 to-slate-50 text-brand/35"
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
              <p class="mt-0.5 text-[10px] tabular-nums text-slate-400">
                {{ post.view_count }} lượt xem
              </p>
            </div>
          </Link>
        </li>
      </ul>
    </section>

    <section
      v-if="sidebar.tags?.length"
      class="kb-blog-panel"
      aria-label="Đám mây thẻ"
    >
      <p class="kb-blog-panel__eyebrow px-4 pt-3.5 pb-2">
        Thẻ
      </p>
      <div class="flex flex-wrap gap-x-2.5 gap-y-2 px-4 pb-4 leading-snug">
        <button
          v-for="tag in sidebar.tags"
          :key="tag.id"
          type="button"
          class="rounded-full px-1.5 py-0.5 text-slate-600 transition hover:bg-brand/[0.06] hover:text-brand"
          :class="[
            tagSizeClass(tag.articles_count, maxTagCount),
            filters.tag === tag.slug ? 'bg-brand/[0.08] font-semibold text-brand' : '',
          ]"
          @click="emit('filter-tag', tag.slug)"
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

.kb-blog-panel__eyebrow {
  @apply text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400;
}
</style>
