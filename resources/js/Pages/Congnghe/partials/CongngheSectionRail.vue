<script setup>
import { computed } from 'vue';
import { useCongngheSectionSpy } from './useCongngheSectionSpy.js';

const props = defineProps({
    links: { type: Array, default: () => [] },
});

const items = computed(() => props.links
    .map((l) => ({
        id: String(l.anchor ?? '').replace(/^#/, ''),
        label: l.label ?? '',
    }))
    .filter((i) => i.id));

// Theo dõi đúng các id thực sự có trong nav (mặc định composable theo dõi full).
const ids = items.value.map((i) => i.id);
const { activeId } = useCongngheSectionSpy(ids.length ? ids : undefined);
</script>

<template>
  <nav
    v-if="items.length"
    class="fixed right-1.5 top-1/2 z-40 -translate-y-1/2 lg:hidden"
    aria-label="Định vị nhanh theo mục"
  >
    <ul class="flex flex-col items-center gap-2 rounded-full border border-white/10 bg-[#070912]/70 px-1.5 py-2.5 backdrop-blur-md">
      <li
        v-for="item in items"
        :key="item.id"
      >
        <a
          :href="`#${item.id}`"
          :aria-label="item.label"
          :aria-current="activeId === item.id ? 'true' : undefined"
          class="block rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/60"
          :class="activeId === item.id
            ? 'h-4 w-1.5 bg-gradient-to-b from-brand to-cyan-300'
            : 'h-1.5 w-1.5 bg-white/25 hover:bg-white/55'"
        />
      </li>
    </ul>
  </nav>
</template>
