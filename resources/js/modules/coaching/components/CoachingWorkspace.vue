<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import CoachingSidebar from './CoachingSidebar.vue';

const drawerOpen = ref(false);
</script>

<template>
  <div class="lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start lg:gap-6">
    <!-- Desktop sidebar (sticky) -->
    <aside class="hidden lg:sticky lg:top-0 lg:block lg:self-start">
      <CoachingSidebar class="lg:max-h-[calc(100vh-7rem)]" />
    </aside>

    <!-- Main column -->
    <div class="min-w-0">
      <button
        type="button"
        class="mb-3 inline-flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-800 lg:hidden"
        @click="drawerOpen = true"
      >
        <AppIcon
          name="list"
          :size="16"
        />
        Khóa &amp; buổi học
      </button>
      <slot />
    </div>

    <!-- Mobile / tablet drawer -->
    <Drawer
      :show="drawerOpen"
      title="Coaching"
      width="max-w-xs"
      flush
      @close="drawerOpen = false"
    >
      <CoachingSidebar
        embedded
        @navigate="drawerOpen = false"
      />
    </Drawer>
  </div>
</template>
