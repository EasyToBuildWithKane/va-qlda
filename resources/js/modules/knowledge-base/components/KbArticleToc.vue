<script setup>
import {
    computed, onMounted, onUnmounted, ref, watch,
} from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    /** plain | panel */
    variant: { type: String, default: 'plain' },
    /** all | mobile | desktop */
    display: { type: String, default: 'all' },
    rootSelector: { type: String, default: null },
});

const showMobile = computed(() => props.display === 'all' || props.display === 'mobile');
const showDesktop = computed(() => props.display === 'all' || props.display === 'desktop');

const activeId = ref('');
const mobileOpen = ref(false);

function scrollTo(id, event) {
    event?.preventDefault();
    const el = document.getElementById(id);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (history.replaceState) {
        history.replaceState(null, '', `#${id}`);
    }
    mobileOpen.value = false;
}

let observer = null;

function setupObserver() {
    observer?.disconnect();
    if (!props.items?.length) return;

    const ids = props.items.map((h) => h.id).filter(Boolean);
    if (!ids.length) return;

    const root = props.rootSelector ? document.querySelector(props.rootSelector) : null;

    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
            if (visible.length) {
                activeId.value = visible[0].target.id;
            }
        },
        {
            root: root || null,
            rootMargin: '-15% 0px -60% 0px',
            threshold: 0,
        },
    );

    for (const id of ids) {
        const el = document.getElementById(id);
        if (el) observer.observe(el);
    }
}

onMounted(() => {
    setupObserver();
});

onUnmounted(() => {
    observer?.disconnect();
});

watch(
    () => props.items,
    () => {
        requestAnimationFrame(setupObserver);
    },
    { deep: true },
);

const panelClass = 'rounded-xl border border-slate-200/90 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50';
</script>

<template>
  <template v-if="items.length">
    <!-- Mobile: collapsible -->
    <nav
      v-if="showMobile"
      class="kb-article-toc kb-article-toc--mobile mb-6 lg:hidden"
      aria-label="Mục lục bài viết"
    >
      <button
        type="button"
        class="flex w-full items-center justify-between rounded-xl border border-slate-200/90 bg-slate-50/90 px-4 py-3 text-left text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200"
        :aria-expanded="mobileOpen"
        @click="mobileOpen = !mobileOpen"
      >
        <span class="inline-flex items-center gap-2">
          <AppIcon
            name="list"
            :size="16"
            class="text-brand"
          />
          Mục lục ({{ items.length }})
        </span>
        <AppIcon
          :name="mobileOpen ? 'chevron-up' : 'chevron-down'"
          :size="16"
          class="text-slate-400"
        />
      </button>
      <ul
        v-show="mobileOpen"
        class="mt-2 space-y-0.5 rounded-xl border border-slate-200/90 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/80"
      >
        <li
          v-for="h in items"
          :key="h.id"
        >
          <a
            :href="`#${h.id}`"
            class="block rounded-md py-1.5 text-sm leading-snug transition-colors"
            :class="[
              h.level === 3 ? 'pl-3' : 'pl-1',
              activeId === h.id
                ? 'font-medium text-brand'
                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100',
            ]"
            @click="scrollTo(h.id, $event)"
          >
            {{ h.text }}
          </a>
        </li>
      </ul>
    </nav>

    <!-- Desktop sidebar -->
    <nav
      v-if="showDesktop"
      class="kb-article-toc kb-article-toc--desktop hidden lg:block"
      :class="variant === 'panel' ? panelClass : ''"
      aria-label="Mục lục bài viết"
    >
      <p class="mb-3 inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Mục lục
        <FieldTooltip text="Nhảy nhanh tới tiêu đề trong bài. Mục đang đọc được tô màu brand." />
      </p>
      <ul class="space-y-0.5 border-l-2 border-slate-200/90 pl-3 dark:border-slate-700">
        <li
          v-for="h in items"
          :key="h.id"
        >
          <a
            :href="`#${h.id}`"
            class="block py-1 text-[13px] leading-snug transition-colors"
            :class="[
              h.level === 3 ? 'pl-2' : '',
              activeId === h.id
                ? 'border-l-2 border-brand -ml-[calc(0.75rem+2px)] pl-[calc(0.5rem+2px)] font-medium text-brand'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200',
            ]"
            @click="scrollTo(h.id, $event)"
          >
            {{ h.text }}
          </a>
        </li>
      </ul>
    </nav>
  </template>
</template>
