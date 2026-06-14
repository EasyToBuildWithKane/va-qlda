<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    manager: { type: Object, default: null },
});

const hasAny = () => props.teams.length || props.manager;
</script>

<template>
  <section
    v-if="hasAny()"
    class="rounded-2xl border border-slate-200/70 bg-white shadow-sm"
  >
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <AppIcon
        name="org-teams"
        :size="16"
        class="text-slate-400"
      />
      <h2 class="text-sm font-semibold text-slate-800">
        Tổ chức
      </h2>
    </header>

    <div class="space-y-4 p-5">
      <div v-if="manager">
        <p class="mb-2 text-[11px] uppercase tracking-wide text-slate-400">
          Quản lý trực tiếp
        </p>
        <div class="flex items-center gap-2.5">
          <Avatar
            :name="manager.name"
            :src="manager.avatar_path"
            :size="36"
          />
          <div class="min-w-0">
            <p class="truncate text-[13px] font-medium text-slate-700">
              {{ manager.name }}
            </p>
            <p
              v-if="manager.role_title"
              class="truncate text-[12px] text-slate-400"
            >
              {{ manager.role_title }}
            </p>
          </div>
        </div>
      </div>

      <div v-if="teams.length">
        <p class="mb-2 text-[11px] uppercase tracking-wide text-slate-400">
          Nhóm tham gia
        </p>
        <ul class="space-y-2">
          <li
            v-for="t in teams"
            :key="t.id"
            class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2"
          >
            <div class="min-w-0">
              <p class="truncate text-[13px] font-medium text-slate-700">
                {{ t.name }}
              </p>
              <p
                v-if="t.section"
                class="truncate text-[11.5px] text-slate-400"
              >
                {{ t.section }}
              </p>
            </div>
            <Badge
              v-if="t.is_leader"
              label="Trưởng nhóm"
              color="amber"
            />
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
