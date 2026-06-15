<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { profileDisplayValue } from '../utils/profileDisplay';
import { useProfileSectionCollapse } from '../composables/useProfileSectionCollapse';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

defineEmits(['edit']);

const leadTeam = computed(() => props.profile.teams?.find((t) => t.is_leader) ?? null);
const primaryTeam = computed(() => props.profile.teams?.[0] ?? null);

const roleTitle = computed(() => profileDisplayValue(props.profile.role_title));
const roleIsEmpty = computed(() => roleTitle.value === profileDisplayValue(null));

const teamLabel = computed(() => {
    if (!primaryTeam.value) {
        return profileDisplayValue(null);
    }
    const suffix = leadTeam.value ? ' · Quản lý nhóm' : '';
    return `${primaryTeam.value.name}${suffix}`;
});

const specRows = computed(() => [
    {
        icon: 'mail',
        label: 'Email',
        value: props.profile.email,
        mono: true,
        href: props.profile.email ? `mailto:${props.profile.email}` : null,
    },
    {
        icon: 'phone',
        label: 'Điện thoại',
        value: props.profile.phone,
        href: props.profile.phone ? `tel:${props.profile.phone}` : null,
    },
    {
        icon: 'settings',
        label: 'Vai trò hệ thống',
        value: props.profile.account_role?.label ?? null,
        badge: props.profile.account_role ?? null,
    },
    {
        icon: 'org-teams',
        label: 'Nhóm QLDA',
        value: primaryTeam.value ? teamLabel.value : null,
    },
    {
        icon: 'performance',
        label: 'Trạng thái',
        value: props.profile.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động',
        status: props.profile.is_active ? 'active' : 'inactive',
    },
]);

function displayValue(raw) {
    return profileDisplayValue(raw);
}

function isEmpty(raw) {
    return displayValue(raw) === profileDisplayValue(null);
}

const { open: specOpen, toggle: toggleSpec } = useProfileSectionCollapse('profile-hero-spec', true);
</script>

<template>
  <section
    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
    aria-label="Thông tin định danh"
  >
    <div
      class="h-1 shrink-0 bg-gradient-to-r from-brand via-[#b81350] to-slate-800"
      aria-hidden="true"
    />

    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 gap-4 sm:gap-5">
          <div class="relative shrink-0">
            <div
              class="rounded-2xl bg-slate-50 p-1.5 ring-1 ring-slate-200/90"
            >
              <Avatar
                :name="profile.name"
                :src="profile.avatar_path"
                :size="88"
              />
            </div>
            <span
              class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full ring-2 ring-white"
              :class="profile.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
              :title="profile.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'"
            />
          </div>

          <div class="min-w-0 flex-1 pt-0.5">
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="font-display text-xl font-semibold leading-tight text-slate-900 sm:text-[1.35rem]">
                {{ profile.name }}
              </h1>
              <Badge
                v-if="profile.seniority?.label"
                :label="profile.seniority.label"
                :color="profile.seniority.color"
              />
            </div>

            <p
              class="mt-1.5 text-sm leading-snug sm:text-[15px]"
              :class="roleIsEmpty ? 'italic text-slate-400' : 'text-slate-600'"
            >
              {{ roleTitle }}
            </p>

            <div class="mt-3 inline-flex flex-wrap items-center gap-2">
              <span
                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-2.5 py-1 font-mono text-[11px] font-medium tabular-nums text-white"
              >
                <AppIcon
                  name="account"
                  :size="12"
                  class="opacity-70"
                />
                {{ profile.code }}
              </span>
            </div>
          </div>
        </div>

        <div
          v-if="editable"
          class="flex shrink-0 lg:pt-1"
        >
          <button
            type="button"
            class="btn-primary inline-flex h-9 w-full items-center justify-center gap-1.5 px-4 text-xs sm:w-auto sm:text-[13px]"
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

      <div class="mt-5 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-2 rounded-lg px-1 py-1 text-left transition-colors hover:bg-slate-50"
          :aria-expanded="specOpen"
          @click="toggleSpec"
        >
          <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            Thông tin liên hệ & vận hành
          </span>
          <AppIcon
            :name="specOpen ? 'chevron-down' : 'chevron-right'"
            :size="16"
            class="shrink-0 text-slate-400"
          />
        </button>

        <dl
          v-show="specOpen"
          class="mt-3 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-slate-200/80 bg-slate-200/80 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
        >
          <div
            v-for="row in specRows"
            :key="row.label"
            class="flex min-w-0 flex-col gap-1 bg-white px-3.5 py-3 sm:px-4"
          >
            <dt class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-400">
              <AppIcon
                :name="row.icon"
                :size="12"
                class="shrink-0 opacity-70"
              />
              {{ row.label }}
            </dt>
            <dd class="min-w-0 text-[13px] leading-snug">
              <Badge
                v-if="row.badge"
                :label="row.badge.label"
                :color="row.badge.color"
              />
              <a
                v-else-if="row.href && !isEmpty(row.value)"
                :href="row.href"
                class="block truncate font-medium text-slate-800 underline decoration-slate-300/80 underline-offset-2 hover:text-brand"
                :class="row.mono ? 'font-mono text-[12px]' : ''"
              >{{ row.value }}</a>
              <span
                v-else-if="row.status"
                class="inline-flex items-center gap-1.5 font-medium"
                :class="row.status === 'active' ? 'text-emerald-700' : 'text-slate-500'"
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="row.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"
                />
                {{ row.value }}
              </span>
              <span
                v-else
                class="block truncate"
                :class="[
                  isEmpty(row.value) ? 'italic text-slate-400' : 'font-medium text-slate-800',
                  row.mono && !isEmpty(row.value) ? 'font-mono text-[12px]' : '',
                ]"
              >{{ displayValue(row.value) }}</span>
            </dd>
          </div>
        </dl>
      </div>
    </div>
  </section>
</template>
