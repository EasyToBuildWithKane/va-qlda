<script setup>
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    manager: { type: Object, default: null },
});

const hasAny = () => props.teams.length || props.manager;
</script>

<template>
  <ProfileInfoPanel
    v-if="hasAny()"
    title="Tổ chức"
    icon="org-teams"
    subtitle="Nhóm QLDA và quản lý trực tiếp"
  >
    <div class="space-y-4 p-5">
      <div v-if="manager">
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Quản lý trực tiếp
        </p>
        <div class="mt-2 flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5">
          <Avatar
            :name="manager.name"
            :src="manager.avatar_path"
            :size="36"
          />
          <div class="min-w-0">
            <p class="truncate text-[13px] font-medium text-slate-800">
              {{ manager.name }}
            </p>
            <p
              v-if="manager.role_title"
              class="truncate text-[12px] text-slate-500"
            >
              {{ manager.role_title }}
            </p>
          </div>
        </div>
      </div>

      <div v-if="teams.length">
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Nhóm tham gia
        </p>
        <ul class="mt-2 space-y-2">
          <li
            v-for="t in teams"
            :key="t.id"
            class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-white px-3 py-2.5"
          >
            <div class="min-w-0">
              <p class="truncate text-[13px] font-medium text-slate-800">
                {{ t.name }}
              </p>
              <p
                v-if="t.section"
                class="truncate font-mono text-[11px] text-slate-400"
              >
                {{ t.section }}
              </p>
            </div>
            <Badge
              v-if="t.is_leader"
              label="Quản lý"
              color="amber"
            />
          </li>
        </ul>
      </div>
    </div>
  </ProfileInfoPanel>
</template>
