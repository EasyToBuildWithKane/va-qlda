<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    /** CSS selector for scroll root; default viewport */
    rootSelector: { type: String, default: null },
});

const activeId = ref('');

function scrollTo(id, event) {
    event?.preventDefault();
    const el = document.getElementById(id);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (history.replaceState) {
        history.replaceState(null, '', `#${id}`);
    }
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
            rootMargin: '-20% 0px -55% 0px',
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
</script>

<template>
  <nav
    v-if="items.length"
    class="hidden lg:block"
    aria-label="Mục lục bài viết"
  >
    <div class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pr-2">
      <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Mục lục
      </p>
      <ul class="space-y-1 border-l border-slate-200/80 pl-3 dark:border-slate-700">
        <li
          v-for="h in items"
          :key="h.id"
        >
          <a
            :href="`#${h.id}`"
            class="block py-1 text-xs leading-snug transition-colors"
            :class="[
              h.level === 3 ? 'pl-2' : '',
              activeId === h.id
                ? 'font-medium text-brand'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200',
            ]"
            @click="scrollTo(h.id, $event)"
          >
            {{ h.text }}
          </a>
        </li>
      </ul>
    </div>
  </nav>
</template>
