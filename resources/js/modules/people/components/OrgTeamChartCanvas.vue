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
    <div
      class="org-chart-canvas__ambient"
      aria-hidden="true"
    >
      <div class="org-chart-canvas__mesh" />
      <div class="org-chart-canvas__grid" />
      <div class="org-chart-canvas__grid-fine" />
      <div class="org-chart-canvas__noise" />
      <div class="org-chart-canvas__orb org-chart-canvas__orb--brand" />
      <div class="org-chart-canvas__orb org-chart-canvas__orb--sky" />
      <div class="org-chart-canvas__orb org-chart-canvas__orb--violet" />
      <div class="org-chart-canvas__scan" />
      <div class="org-chart-canvas__scan org-chart-canvas__scan--delayed" />
      <div class="org-chart-canvas__vignette" />
    </div>

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
        <p class="org-chart-canvas__status">
          <span class="org-chart-canvas__status-dot" />
          Sơ đồ trực tiếp
        </p>
      </div>
      <p class="org-chart-canvas__legend">
        Đường nối:
        <span class="org-chart-canvas__legend-flow">Nhóm</span>
        <span
          class="org-chart-canvas__legend-line-v"
          aria-hidden="true"
        />
        <span class="org-chart-canvas__legend-flow">Trưởng nhóm</span>
        <span
          class="org-chart-canvas__legend-line-v"
          aria-hidden="true"
        />
        <span class="org-chart-canvas__legend-flow">Thành viên</span>
      </p>
    </header>

    <div class="org-chart-canvas__viewport">
      <slot />
    </div>
  </div>
</template>
