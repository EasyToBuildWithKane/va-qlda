<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

defineEmits(['edit']);

const leadTeam = computed(() => props.profile.teams?.find((t) => t.is_leader) ?? null);
const primaryTeam = computed(() => props.profile.teams?.[0] ?? null);
const currentProject = computed(() => props.profile.current_projects?.[0] ?? null);

const facts = computed(() => {
    const out = [];
    if (primaryTeam.value) {
        out.push({
            icon: 'org-teams',
            label: 'Nhóm',
            value: primaryTeam.value.name + (leadTeam.value ? ' · Trưởng nhóm' : ''),
        });
    }
    if (props.profile.manager) {
        out.push({ icon: 'account', label: 'Quản lý', value: props.profile.manager.name });
    }
    if (props.profile.join_date) {
        out.push({ icon: 'calendar', label: 'Ngày tham gia', value: date(props.profile.join_date) });
    }
    if (currentProject.value) {
        out.push({ icon: 'projects', label: 'Dự án hiện tại', value: currentProject.value.name });
    }
    return out;
});
</script>

<template>
  <section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <!-- Cover band -->
    <div class="h-24 bg-gradient-to-r from-brand via-brand to-brand/70 sm:h-28" />

    <div class="px-5 pb-5 sm:px-7">
      <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-end gap-4">
          <!-- Avatar -->
          <div class="rounded-full bg-white p-1 shadow-md ring-1 ring-slate-200/70">
            <Avatar
              :name="profile.name"
              :src="profile.avatar_path"
              :size="96"
            />
          </div>
          <div class="pb-1">
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-xl font-semibold leading-tight text-slate-900 font-display">
                {{ profile.name }}
              </h1>
              <Badge
                :label="profile.seniority.label"
                :color="profile.seniority.color"
              />
            </div>
            <p class="mt-1 text-sm text-slate-500">
              {{ profile.role_title || 'Chưa cập nhật chức danh' }}
            </p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex shrink-0 items-center gap-2">
          <button
            v-if="editable"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-700 transition-colors hover:bg-slate-50"
            @click="$emit('edit')"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Chỉnh sửa hồ sơ
          </button>
        </div>
      </div>

      <!-- Meta chips -->
      <div class="mt-4 flex flex-wrap items-center gap-2 text-[12px]">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">
          <AppIcon
            name="account"
            :size="13"
            class="text-slate-400"
          />
          {{ profile.code }}
        </span>
        <Badge
          v-if="profile.account_role"
          :label="profile.account_role.label"
          :color="profile.account_role.color"
        />
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
          :class="profile.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
        >
          <span
            class="h-1.5 w-1.5 rounded-full"
            :class="profile.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
          />
          {{ profile.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}
        </span>
      </div>

      <!-- Quick facts -->
      <div
        v-if="facts.length"
        class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-4"
      >
        <div
          v-for="f in facts"
          :key="f.label"
          class="flex items-start gap-2.5"
        >
          <div class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
            <AppIcon
              :name="f.icon"
              :size="14"
            />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] uppercase tracking-wide text-slate-400">
              {{ f.label }}
            </p>
            <p class="truncate text-[13px] font-medium text-slate-700">
              {{ f.value }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
