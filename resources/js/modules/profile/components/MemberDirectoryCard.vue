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
    class="group flex flex-col rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md"
  >
    <div class="flex items-start gap-3">
      <Avatar
        :name="member.name"
        :src="member.avatar_path"
        :size="48"
      />
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5">
          <h3 class="truncate text-[14px] font-semibold text-slate-800 group-hover:text-brand">
            {{ member.name }}
          </h3>
          <span
            v-if="!member.is_active"
            class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-300"
            title="Ngừng hoạt động"
          />
        </div>
        <p class="truncate text-[12px] text-slate-400">
          {{ member.role_title || 'Chưa cập nhật chức danh' }}
        </p>
        <div class="mt-1.5">
          <Badge
            :label="member.seniority.label"
            :color="member.seniority.color"
          />
        </div>
      </div>
    </div>

    <div
      v-if="member.skills_preview.length"
      class="mt-3 flex flex-wrap gap-1"
    >
      <span
        v-for="s in member.skills_preview"
        :key="s"
        class="rounded-md bg-slate-50 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 ring-1 ring-inset ring-slate-100"
      >
        {{ s }}
      </span>
      <span
        v-if="member.skills_total > member.skills_preview.length"
        class="rounded-md px-1.5 py-0.5 text-[11px] font-medium text-slate-400"
      >
        +{{ member.skills_total - member.skills_preview.length }}
      </span>
    </div>

    <div class="mt-3 flex items-center gap-3 border-t border-slate-100 pt-3 text-[11.5px] text-slate-400">
      <span class="inline-flex items-center gap-1">
        <AppIcon
          name="account"
          :size="12"
        />
        {{ member.code }}
      </span>
      <span class="inline-flex items-center gap-1">
        <AppIcon
          name="projects"
          :size="12"
        />
        {{ member.projects_count }} dự án
      </span>
    </div>
  </Link>
</template>
