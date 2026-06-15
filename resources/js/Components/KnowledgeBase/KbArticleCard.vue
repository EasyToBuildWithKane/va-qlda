<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { richContentPlainText } from '@/shared/utils/richContent';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    article: { type: Object, required: true },
    variant: {
        type: String,
        default: 'grid',
        validator: (v) => ['grid', 'list'].includes(v),
    },
    showStatus: { type: Boolean, default: true },
    showCategory: { type: Boolean, default: true },
    showExcerpt: { type: Boolean, default: true },
    showTags: { type: Boolean, default: false },
    showViews: { type: Boolean, default: true },
    showAuthor: { type: Boolean, default: false },
    showUpdated: { type: Boolean, default: false },
});

const href = `/knowledge-base/articles/${props.article.slug}`;

const excerptText = computed(() => {
    const raw = props.article.excerpt?.trim() || richContentPlainText(props.article.content);
    if (!raw) return '';
    const plain = richContentPlainText(raw);
    return plain.length > 220 ? `${plain.slice(0, 217)}…` : plain;
});

const metaItems = computed(() => {
    const items = [];
    if (props.showViews) {
        items.push({ key: 'views', icon: 'eye', text: `${props.article.view_count ?? 0} lượt xem` });
    }
    if (props.showAuthor && props.article.author?.full_name) {
        items.push({ key: 'author', icon: 'account', text: props.article.author.full_name });
    }
    if (props.showUpdated && props.article.updated_at) {
        items.push({ key: 'updated', icon: 'clock', text: datetime(props.article.updated_at) });
    }
    return items;
});
</script>

<template>
  <Link
    :href="href"
    class="group relative flex h-full overflow-hidden rounded-card border border-slate-200/90 bg-white shadow-sm transition hover:border-brand/35 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand/40"
    :class="variant === 'list' ? 'flex-row gap-0 sm:gap-4' : 'flex-col'"
  >
    <div
      class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-brand/80 to-brand/30 opacity-80 transition group-hover:opacity-100"
      aria-hidden="true"
    />

    <div
      class="flex min-w-0 flex-1 flex-col"
      :class="variant === 'list' ? 'py-4 pl-5 pr-4 sm:pl-6' : 'p-5 pl-6'"
    >
      <div
        v-if="showStatus || showCategory"
        class="mb-2 flex flex-wrap items-center gap-2"
      >
        <Badge
          v-if="showStatus && article.status"
          :label="article.status.label"
          color="slate"
        />
        <span
          v-if="showCategory && article.category"
          class="inline-flex items-center gap-1 rounded-full bg-slate-100/90 px-2.5 py-0.5 text-[11px] font-medium text-slate-600"
        >
          <AppIcon
            name="documents"
            :size="12"
            class="text-slate-400"
          />
          {{ article.category.name }}
        </span>
      </div>

      <h3
        class="font-display font-semibold text-slate-800 transition group-hover:text-brand"
        :class="variant === 'list' ? 'text-base leading-snug' : 'text-[1.05rem] leading-snug'"
      >
        {{ article.title }}
      </h3>

      <p
        v-if="showExcerpt && excerptText"
        class="mt-2 line-clamp-2 flex-1 text-sm leading-relaxed text-slate-500"
      >
        {{ excerptText }}
      </p>

      <div
        v-if="showTags && article.tags?.length"
        class="mt-3 flex flex-wrap gap-1.5"
      >
        <span
          v-for="t in article.tags.slice(0, 4)"
          :key="t.id"
          class="rounded-full bg-brand/5 px-2 py-0.5 text-[10px] font-medium text-brand/90"
        >
          #{{ t.name }}
        </span>
      </div>

      <div
        v-if="metaItems.length"
        class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-400"
        :class="variant === 'grid' ? 'mt-4' : 'mt-3'"
      >
        <span
          v-for="m in metaItems"
          :key="m.key"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            :name="m.icon"
            :size="13"
            class="text-slate-300"
          />
          {{ m.text }}
        </span>
      </div>
    </div>

    <div
      v-if="variant === 'list'"
      class="hidden shrink-0 items-center pr-4 text-slate-300 transition group-hover:text-brand sm:flex"
      aria-hidden="true"
    >
      <AppIcon
        name="chevron-right"
        :size="18"
      />
    </div>
  </Link>
</template>
