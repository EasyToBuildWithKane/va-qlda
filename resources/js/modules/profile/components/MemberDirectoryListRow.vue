<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';

defineProps({
    member: { type: Object, required: true },
});
</script>

<template>
  <Link
    :href="`/members/${member.id}`"
    class="group flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white px-4 py-3 shadow-sm transition-all hover:border-brand/25 hover:bg-brand/[0.02] hover:shadow-md sm:gap-4"
  >
    <Avatar
      :name="member.name"
      :src="member.avatar_path"
      :size="44"
      class="shrink-0"
    />

    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
        <h3 class="truncate text-sm font-semibold text-slate-800 group-hover:text-brand">
          {{ member.name }}
        </h3>
        <span class="font-mono text-[11px] text-slate-400">{{ member.code }}</span>
        <Badge
          :label="member.seniority.label"
          :color="member.seniority.color"
        />
        <span
          v-if="!member.is_active"
          class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
        >
          Ngừng
        </span>
      </div>
      <p class="mt-0.5 truncate text-xs text-slate-500">
        {{ member.role_title || 'Chưa cập nhật chức danh' }}
        <template v-if="member.email">
          · {{ member.email }}
        </template>
      </p>
      <div
        v-if="member.skills_preview?.length"
        class="mt-1.5 flex flex-wrap gap-1"
      >
        <span
          v-for="s in member.skills_preview.slice(0, 4)"
          :key="s"
          class="rounded-md bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 ring-1 ring-inset ring-slate-100"
        >
          {{ s }}
        </span>
        <span
          v-if="member.skills_total > 4"
          class="text-[10px] font-medium text-slate-400"
        >
          +{{ member.skills_total - 4 }}
        </span>
      </div>
    </div>

    <div class="hidden shrink-0 flex-col items-end gap-1 text-right sm:flex">
      <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-600">
        <AppIcon
          name="projects"
          :size="13"
          class="text-slate-400"
        />
        {{ member.projects_count }}
        <span class="font-normal text-slate-400">dự án</span>
      </span>
      <AppIcon
        name="chevron-right"
        :size="16"
        class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand/60"
      />
    </div>
  </Link>
</template>
