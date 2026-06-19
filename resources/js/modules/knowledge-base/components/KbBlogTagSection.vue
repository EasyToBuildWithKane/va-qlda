<script setup>
import { computed } from 'vue';
import KbBlogPanel from '@/modules/knowledge-base/components/KbBlogPanel.vue';

const props = defineProps({
    tags: { type: Array, default: () => [] },
    activeSlug: { type: String, default: '' },
    /** sidebar compact list | cloud weighted */
    layout: { type: String, default: 'chips' },
});

const emit = defineEmits(['filter-tag']);

const maxCount = computed(() => {
    const counts = props.tags.map((t) => t.articles_count ?? 0);
    return counts.length ? Math.max(...counts) : 0;
});

function weightClass(count) {
    const max = maxCount.value;
    if (max <= 0) return 'text-xs';
    const ratio = count / max;
    if (ratio >= 0.75) return 'text-sm font-semibold';
    if (ratio >= 0.4) return 'text-xs font-medium';
    return 'text-[11px]';
}
</script>

<template>
  <KbBlogPanel
    v-if="tags.length"
    aria-label="Thẻ bài viết"
    title="Thẻ"
    variant="tags"
  >
    <p class="mb-2.5 text-[11px] leading-snug text-slate-500">
      Bấm thẻ để lọc — bấm lại để bỏ lọc
    </p>
    <ul
      class="m-0 flex list-none flex-wrap gap-2 p-0"
      role="list"
    >
      <li
        v-for="tag in tags"
        :key="tag.id"
      >
        <button
          type="button"
          class="kb-tag-chip group inline-flex max-w-full items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-left transition"
          :class="[
            layout === 'cloud' ? weightClass(tag.articles_count ?? 0) : 'text-xs',
            activeSlug === tag.slug
              ? 'kb-tag-chip--active border-brand/35 bg-brand/[0.07] text-brand shadow-[0_0_0_1px_rgba(154,0,54,0.08)]'
              : 'border-slate-200/90 bg-white text-slate-600 hover:border-brand/20 hover:bg-brand/[0.04] hover:text-brand',
          ]"
          :aria-pressed="activeSlug === tag.slug"
          @click="emit('filter-tag', tag.slug)"
        >
          <span
            class="shrink-0 font-mono text-[10px] font-semibold opacity-60"
            aria-hidden="true"
          >#</span>
          <span class="min-w-0 truncate">{{ tag.name }}</span>
          <span
            v-if="tag.articles_count != null"
            class="shrink-0 rounded-md bg-slate-100 px-1.5 py-px font-mono text-[10px] tabular-nums text-slate-500 group-hover:bg-white/80"
            :class="activeSlug === tag.slug ? 'bg-brand/10 text-brand/80' : ''"
          >
            {{ tag.articles_count }}
          </span>
        </button>
      </li>
    </ul>
  </KbBlogPanel>
</template>

<style scoped>
.kb-tag-chip {
  min-height: 2rem;
}

.kb-tag-chip--active {
  @apply font-medium;
}
</style>
