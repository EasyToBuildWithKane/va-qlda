<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { richContentPlainText } from '@/shared/utils/richContent';
import { datetime, date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    article: { type: Object, required: true },
    showStatus: { type: Boolean, default: true },
    showCategory: { type: Boolean, default: true },
    showExcerpt: { type: Boolean, default: true },
    showTags: { type: Boolean, default: true },
    showViews: { type: Boolean, default: true },
    showAuthor: { type: Boolean, default: false },
    showUpdated: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();
const deleting = ref(false);

const href = computed(() => `/knowledge-base/articles/${props.article.slug}`);

const excerptText = computed(() => {
    const raw = props.article.excerpt?.trim() || richContentPlainText(props.article.content);
    if (!raw) return '';
    const plain = richContentPlainText(raw);
    return plain.length > 160 ? `${plain.slice(0, 157)}…` : plain;
});

const categoryColor = computed(() => props.article.category?.color || null);

async function confirmDelete() {
    if (deleting.value || !props.article.can?.delete) return;
    const ok = await dialog.confirm({
        title: 'Xóa bài viết',
        message: `Bài «${props.article.title}» sẽ bị xóa vĩnh viễn. Tiếp tục?`,
        tone: 'danger',
        confirmText: 'Xóa',
    });
    if (!ok) return;
    deleting.value = true;
    router.delete(route('knowledge-base.articles.destroy', props.article.slug), {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa bài viết.'),
        onError: () => toast.error('Không xóa được bài viết.'),
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
  <article
    class="group relative flex h-full flex-col overflow-hidden rounded-card border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-100/80 transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-lg"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand via-brand/70 to-brand/30"
      aria-hidden="true"
    />

    <div
      v-if="article.can?.delete || article.can?.update"
      class="absolute right-2 top-2 z-20 flex gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100 sm:focus-within:opacity-100"
    >
      <Link
        v-if="article.can?.update"
        :href="`/knowledge-base/articles/${article.slug}/edit`"
        class="pointer-events-auto inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200/90 bg-white/95 text-slate-600 shadow-sm backdrop-blur hover:border-brand/30 hover:text-brand"
        title="Chỉnh sửa"
        aria-label="Chỉnh sửa bài viết"
        @click.stop
      >
        <AppIcon
          name="edit"
          :size="15"
        />
      </Link>
      <button
        v-if="article.can?.delete"
        type="button"
        class="pointer-events-auto inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200/90 bg-white/95 text-slate-500 shadow-sm backdrop-blur hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50"
        title="Xóa bài"
        aria-label="Xóa bài viết"
        :disabled="deleting"
        @click.stop="confirmDelete"
      >
        <AppIcon
          name="delete"
          :size="15"
        />
      </button>
    </div>

    <Link
      :href="href"
      class="block shrink-0 overflow-hidden"
    >
      <div class="relative aspect-[16/10] w-full bg-slate-100">
        <img
          v-if="article.cover_url"
          :src="article.cover_url"
          alt=""
          class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
          loading="lazy"
        >
        <div
          v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-brand/[0.08] via-slate-50 to-white"
        >
          <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/80 text-brand/40 shadow-sm ring-1 ring-slate-200/80">
            <AppIcon
              name="knowledge"
              :size="26"
            />
          </div>
        </div>
        <div
          v-if="showCategory && article.category"
          class="absolute bottom-2 left-2 max-w-[85%] truncate rounded-md px-2 py-1 text-[11px] font-semibold text-white shadow-sm backdrop-blur-sm"
          :class="categoryColor ? '' : 'bg-slate-900/75'"
          :style="categoryColor ? { backgroundColor: `${categoryColor}dd` } : undefined"
        >
          {{ article.category.name }}
        </div>
      </div>
    </Link>

    <div class="flex min-h-0 flex-1 flex-col p-4 pt-3.5">
      <div
        v-if="showStatus && article.status"
        class="mb-2"
      >
        <Badge
          :label="article.status.label"
          color="slate"
        />
      </div>

      <h3 class="font-display text-base font-semibold leading-snug text-slate-900">
        <Link
          :href="href"
          class="line-clamp-2 transition hover:text-brand"
        >
          {{ article.title }}
        </Link>
      </h3>

      <p
        v-if="showExcerpt && excerptText"
        class="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-slate-500"
      >
        {{ excerptText }}
      </p>

      <div
        v-if="showTags && article.tags?.length"
        class="mt-3 flex flex-wrap gap-1"
      >
        <span
          v-for="t in article.tags.slice(0, 3)"
          :key="t.id"
          class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600"
        >
          #{{ t.name }}
        </span>
      </div>

      <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-[11px] text-slate-400">
        <span
          v-if="showViews"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            name="eye"
            :size="12"
          />
          {{ article.view_count ?? 0 }}
        </span>
        <span
          v-if="article.published_at"
          class="inline-flex items-center gap-1"
        >
          <AppIcon
            name="calendar"
            :size="12"
          />
          {{ date(article.published_at) }}
        </span>
        <span
          v-if="showAuthor && article.author?.full_name"
          class="truncate"
        >
          {{ article.author.full_name }}
        </span>
        <span v-if="showUpdated && article.updated_at">
          {{ datetime(article.updated_at) }}
        </span>
      </div>
    </div>
  </article>
</template>
