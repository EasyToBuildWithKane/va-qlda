<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import KbArticleToc from '@/Components/KnowledgeBase/KbArticleToc.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    tocItems: { type: Array, default: () => [] },
    isFavorite: { type: Boolean, default: false },
    favoriting: { type: Boolean, default: false },
    shareUrl: { type: String, default: '' },
    shareTitle: { type: String, default: '' },
});

const emit = defineEmits(['toggle-favorite']);

const toast = useToast();

async function shareArticle() {
    const payload = { title: props.shareTitle, url: props.shareUrl };
    try {
        if (typeof navigator !== 'undefined' && navigator.share) {
            await navigator.share(payload);
            return;
        }
    } catch {
        /* cancelled */
    }
    await copyLink();
}

async function copyLink() {
    if (!props.shareUrl) return;
    try {
        await navigator.clipboard.writeText(props.shareUrl);
        toast.success('Đã sao chép liên kết.');
    } catch {
        toast.error('Không sao chép được liên kết.');
    }
}

function printArticle() {
    window.print();
}
</script>

<template>
  <aside
    class="kb-article-sidebar hidden w-full shrink-0 lg:block lg:w-[260px]"
    aria-label="Mục lục và thao tác"
  >
    <div class="sticky top-24 flex max-h-[calc(100vh-6.5rem)] flex-col gap-4 overflow-y-auto pb-4">
      <KbArticleToc
        v-if="tocItems.length"
        :items="tocItems"
        variant="panel"
      />

      <div
        class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50"
      >
        <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
          Thao tác
        </p>
        <ul class="space-y-1">
          <li>
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm text-slate-600 transition hover:bg-white hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800"
              :class="isFavorite ? 'font-medium text-amber-700 dark:text-amber-400' : ''"
              :disabled="favoriting"
              @click="emit('toggle-favorite')"
            >
              <AppIcon
                name="star"
                :size="16"
              />
              {{ isFavorite ? 'Đã lưu yêu thích' : 'Lưu bài viết' }}
            </button>
          </li>
          <li>
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm text-slate-600 transition hover:bg-white hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800"
              @click="shareArticle"
            >
              <AppIcon
                name="link"
                :size="16"
              />
              Chia sẻ
            </button>
          </li>
          <li>
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm text-slate-600 transition hover:bg-white hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800"
              @click="copyLink"
            >
              <AppIcon
                name="documents"
                :size="16"
              />
              Sao chép liên kết
            </button>
          </li>
          <li>
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm text-slate-600 transition hover:bg-white hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800"
              @click="printArticle"
            >
              <AppIcon
                name="export"
                :size="16"
              />
              In bài viết
            </button>
          </li>
        </ul>
      </div>
    </div>
  </aside>
</template>
