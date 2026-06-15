<script setup>
import EmptyState from '@/shared/ui/EmptyState.vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';

defineProps({
    projects: { type: Array, default: () => [] },
    sectionKey: { type: String, default: 'profile-projects-list' },
});

const roleLabel = {
    manager: 'Quản lý',
    lead: 'Trưởng nhóm',
    member: 'Thành viên',
    reviewer: 'Reviewer',
    viewer: 'Theo dõi',
};
</script>

<template>
  <ProfileInfoPanel
    title="Dự án đang tham gia"
    icon="projects"
    :section-key="sectionKey"
    :collapsed-badge="projects.length ? `${projects.length} dự án` : null"
  >
    <div class="p-5">
      <ul
        v-if="projects.length"
        class="space-y-2.5"
      >
        <li
          v-for="p in projects"
          :key="p.id"
          class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5"
        >
          <span
            class="h-8 w-1.5 shrink-0 rounded-full"
            :style="{ backgroundColor: p.color || '#9a0036' }"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium text-slate-800">
              {{ p.name }}
            </p>
            <p class="truncate text-[11.5px] text-slate-400">
              {{ p.code }}
            </p>
          </div>
          <span
            v-if="p.role"
            class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
          >
            {{ roleLabel[p.role] || p.role }}
          </span>
          <span
            v-if="p.allocation"
            class="shrink-0 text-[11px] font-medium text-slate-400"
          >{{ p.allocation }}%</span>
        </li>
      </ul>
      <EmptyState
        v-else
        icon="projects"
        title="Chưa tham gia dự án"
        description="Khi được phân vào dự án, danh sách sẽ hiển thị tại đây."
      />
    </div>
  </ProfileInfoPanel>
</template>
