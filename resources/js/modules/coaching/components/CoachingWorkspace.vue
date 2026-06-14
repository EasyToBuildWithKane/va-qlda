<script setup>
/*
 * Coaching content shell.
 *
 * Previously hosted a course-list sidebar (desktop aside + mobile drawer).
 * That sidebar was removed — every coaching page now renders full width as a
 * single column. Kept as a thin wrapper so pages share one content shell and a
 * sidebar/layout can be reintroduced here without touching each page.
 */
</script>

<template>
  <div class="min-w-0">
    <slot />
  </div>
</template>
