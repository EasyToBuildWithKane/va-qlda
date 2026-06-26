<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    teamScope: { type: Object, default: null },
});
</script>

<template>
  <div
    v-if="teamScope"
    class="mb-4 rounded-card border border-slate-200/80 bg-gradient-to-r from-slate-50/90 to-white px-4 py-3 text-sm shadow-sm sm:px-5"
  >
    <div class="flex flex-wrap items-start gap-x-6 gap-y-2">
      <div class="min-w-0 flex-1">
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-brand/80">
          Phạm vi nhóm
        </p>
        <p class="mt-0.5 font-display text-sm font-semibold text-slate-800">
          {{ teamScope.scopeLabel }}
        </p>
        <p class="mt-1 text-[11px] leading-snug text-slate-500">
          {{ teamScope.groupingHint }}
        </p>
      </div>
      <div
        v-if="(teamScope.ledTeams ?? []).length"
        class="flex min-w-0 flex-wrap items-center gap-1.5"
      >
        <span class="text-[11px] font-medium text-slate-500">Nhóm tổ chức:</span>
        <span
          v-for="t in teamScope.ledTeams"
          :key="t.id"
          class="inline-flex items-center gap-1 rounded-full bg-brand/5 px-2 py-0.5 text-[11px] font-medium text-brand ring-1 ring-brand/15"
        >
          <AppIcon
            name="people"
            :size="11"
          />
          {{ t.name }}
        </span>
      </div>
    </div>
  </div>
</template>
