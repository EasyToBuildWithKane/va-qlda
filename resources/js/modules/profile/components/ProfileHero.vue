<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

defineEmits(['edit']);

const leadTeam = computed(() => props.profile.teams?.find((t) => t.is_leader) ?? null);
const primaryTeam = computed(() => props.profile.teams?.[0] ?? null);
</script>

<template>
  <section class="profile-hero">
    <!-- Cover -->
    <div class="profile-hero__cover">
      <div
        class="profile-hero__cover-grid"
        aria-hidden="true"
      />
      <div
        class="profile-hero__cover-orb"
        aria-hidden="true"
      />
    </div>

    <div class="px-5 pb-5 sm:px-7">
      <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-end gap-4">
          <div class="rounded-full bg-white p-1 shadow-md ring-1 ring-slate-200/70">
            <Avatar
              :name="profile.name"
              :src="profile.avatar_path"
              :size="96"
            />
          </div>
          <div class="pb-1">
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="font-display text-xl font-semibold leading-tight text-slate-900">
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

      <!-- Identity chips -->
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
          v-if="primaryTeam"
          class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600"
        >
          <AppIcon
            name="org-teams"
            :size="13"
            class="text-slate-400"
          />
          {{ primaryTeam.name }}{{ leadTeam ? ' · Quản lý' : '' }}
        </span>
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
    </div>
  </section>
</template>

<style scoped>
.profile-hero {
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #fff;
    box-shadow: 0 10px 30px -22px rgba(15, 23, 42, 0.5);
}

.profile-hero__cover {
    position: relative;
    height: 7rem;
    overflow: hidden;
    background: linear-gradient(120deg, #9a0036 0%, #b81350 55%, #d6418a 100%);
}

.profile-hero__cover-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
    background-size: 26px 26px;
    mask-image: radial-gradient(120% 100% at 80% 0%, #000, transparent 75%);
}

.profile-hero__cover-orb {
    position: absolute;
    top: -40%;
    right: 8%;
    width: 220px;
    height: 220px;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.28), transparent 70%);
}
</style>
