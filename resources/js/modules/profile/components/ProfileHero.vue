<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { profileDisplayValue } from '../utils/profileDisplay';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

defineEmits(['edit']);

const leadTeam = computed(() => props.profile.teams?.find((t) => t.is_leader) ?? null);
const primaryTeam = computed(() => props.profile.teams?.[0] ?? null);
const roleLine = computed(() => profileDisplayValue(props.profile.role_title));
const roleEmpty = computed(() => roleLine.value === profileDisplayValue(null));
</script>

<template>
  <section class="profile-hero">
    <div class="profile-hero__cover">
      <div
        class="profile-hero__cover-grid"
        aria-hidden="true"
      />
      <div
        class="profile-hero__cover-orb"
        aria-hidden="true"
      />
      <div
        class="profile-hero__cover-scan"
        aria-hidden="true"
      />
    </div>

    <div class="profile-hero__body px-5 pb-5 pt-0 sm:px-7">
      <div class="profile-hero__identity -mt-14 flex flex-col gap-5 sm:-mt-16 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end">
          <div class="profile-hero__avatar-wrap shrink-0 self-start rounded-full bg-white p-1 shadow-lg ring-2 ring-white">
            <Avatar
              :name="profile.name"
              :src="profile.avatar_path"
              :size="96"
            />
          </div>
          <div class="min-w-0 pb-0.5 sm:pb-1">
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="font-display text-xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-2xl">
                {{ profile.name }}
              </h1>
              <Badge
                :label="profile.seniority.label"
                :color="profile.seniority.color"
              />
            </div>
            <p
              class="mt-1.5 text-sm leading-snug"
              :class="roleEmpty ? 'italic text-slate-400' : 'text-slate-600'"
            >
              {{ roleLine }}
            </p>
            <p class="mt-1 font-mono text-[11px] tabular-nums text-slate-400">
              {{ profile.code }}
            </p>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:pb-1">
          <button
            v-if="editable"
            type="button"
            class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs sm:text-[13px]"
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

      <div
        class="profile-hero__meta mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4"
        aria-label="Thông tin nhanh"
      >
        <div class="profile-hero__meta-item">
          <span class="profile-hero__meta-label">Vai trò hệ thống</span>
          <span class="profile-hero__meta-value">
            <Badge
              v-if="profile.account_role"
              :label="profile.account_role.label"
              :color="profile.account_role.color"
            />
            <span
              v-else
              class="italic text-slate-400"
            >( chưa cập nhật )</span>
          </span>
        </div>
        <div class="profile-hero__meta-item">
          <span class="profile-hero__meta-label">Nhóm QLDA</span>
          <span
            class="profile-hero__meta-value truncate"
            :class="primaryTeam ? 'text-slate-800' : 'italic text-slate-400'"
          >
            {{ primaryTeam ? `${primaryTeam.name}${leadTeam ? ' · Quản lý' : ''}` : '( chưa cập nhật )' }}
          </span>
        </div>
        <div class="profile-hero__meta-item">
          <span class="profile-hero__meta-label">Trạng thái</span>
          <span
            class="profile-hero__meta-value inline-flex items-center gap-1.5"
            :class="profile.is_active ? 'text-emerald-700' : 'text-slate-500'"
          >
            <span
              class="h-1.5 w-1.5 rounded-full"
              :class="profile.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
            />
            {{ profile.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}
          </span>
        </div>
        <div class="profile-hero__meta-item">
          <span class="profile-hero__meta-label">Email</span>
          <span
            class="profile-hero__meta-value truncate font-mono text-[12px]"
            :class="profile.email ? 'text-slate-800' : 'italic text-slate-400'"
          >
            {{ profile.email || '( chưa cập nhật )' }}
          </span>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.profile-hero {
    overflow: hidden;
    border-radius: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #fff;
    box-shadow: 0 12px 40px -28px rgba(15, 23, 42, 0.45);
}

.profile-hero__cover {
    position: relative;
    height: 8.5rem;
    overflow: hidden;
    background: linear-gradient(125deg, #0f172a 0%, #1e293b 42%, #9a0036 100%);
}

.profile-hero__cover-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
    background-size: 24px 24px;
    mask-image: linear-gradient(180deg, #000 30%, transparent 95%);
}

.profile-hero__cover-orb {
    position: absolute;
    top: -55%;
    right: 5%;
    width: 240px;
    height: 240px;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(154, 0, 54, 0.45), transparent 68%);
}

.profile-hero__cover-scan {
    position: absolute;
    inset: auto 0 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent);
}

.profile-hero__meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border: 1px solid rgb(241 245 249);
    background: rgb(248 250 252 / 0.65);
    padding: 0.65rem 0.75rem;
    border-radius: 0.75rem;
}

.profile-hero__meta-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgb(148 163 184);
}

.profile-hero__meta-value {
    min-height: 1.25rem;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.35;
}
</style>
