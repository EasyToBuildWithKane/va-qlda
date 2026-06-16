<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    isFavorite: { type: Boolean, default: false },
    favoriting: { type: Boolean, default: false },
    shareUrl: { type: String, required: true },
    shareTitle: { type: String, default: '' },
});

const emit = defineEmits(['toggle-favorite']);

const toast = useToast();
const liked = ref(false);

async function shareArticle() {
    const payload = { title: props.shareTitle, url: props.shareUrl };
    try {
        if (typeof navigator !== 'undefined' && navigator.share) {
            await navigator.share(payload);
            return;
        }
    } catch {
        /* user cancelled */
    }
    await copyLink();
}

async function copyLink() {
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
    class="hidden lg:flex lg:justify-end"
    aria-label="Thanh công cụ bài viết"
  >
    <div class="sticky top-24 flex flex-col gap-2">
      <button
        type="button"
        class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
        :class="isFavorite ? 'border-amber-300/80 text-amber-600' : ''"
        :disabled="favoriting"
        title="Lưu bài"
        @click="emit('toggle-favorite')"
      >
        <AppIcon
          name="star"
          :size="18"
        />
      </button>
      <button
        type="button"
        class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
        title="Chia sẻ"
        @click="shareArticle"
      >
        <AppIcon
          name="link"
          :size="18"
        />
      </button>
      <button
        type="button"
        class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
        title="Sao chép liên kết"
        @click="copyLink"
      >
        <AppIcon
          name="documents"
          :size="18"
        />
      </button>
      <button
        type="button"
        class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 text-slate-600 shadow-sm backdrop-blur-sm transition hover:border-brand/30 hover:text-brand dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300"
        title="In bài viết"
        @click="printArticle"
      >
        <AppIcon
          name="export"
          :size="18"
        />
      </button>
      <button
        type="button"
        class="grid h-11 w-11 place-items-center rounded-full border border-slate-200/80 bg-white/90 shadow-sm backdrop-blur-sm transition hover:border-rose-300/80 dark:border-slate-700 dark:bg-slate-900/90"
        :class="liked ? 'text-rose-600' : 'text-slate-600 dark:text-slate-300'"
        title="Thích bài viết"
        @click="liked = !liked"
      >
        <AppIcon
          name="check-circle"
          :size="18"
        />
      </button>
    </div>
  </aside>
</template>
