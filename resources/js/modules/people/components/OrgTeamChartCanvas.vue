<script setup>
defineProps({
    title: { type: String, default: '' },
    levelLabel: { type: String, default: '' },
});
</script>

<template>
  <div
    class="org-chart-canvas"
    role="region"
    aria-label="Sơ đồ tổ chức team"
  >
    <header
      v-if="title || levelLabel"
      class="org-chart-canvas__header"
    >
      <div class="min-w-0">
        <p
          v-if="levelLabel"
          class="org-chart-canvas__eyebrow"
        >
          {{ levelLabel }}
        </p>
        <p
          v-if="title"
          class="org-chart-canvas__title"
        >
          {{ title }}
        </p>
      </div>
      <p class="org-chart-canvas__hint">
        Bấm thẻ nhóm để sửa · thêm nhóm con từ menu trên thẻ
      </p>
    </header>

    <div class="org-chart-canvas__viewport">
      <slot />
    </div>
  </div>
</template>
