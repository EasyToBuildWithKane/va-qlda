<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { profileDisplayValue, isProfileEmpty } from '../utils/profileDisplay';

const props = defineProps({
    profile: { type: Object, required: true },
});

const hr = computed(() => props.profile.hr_info ?? {});

const roleTitleRaw = computed(
    () => hr.value.position_name || props.profile.role_title || null,
);
const roleTitle = computed(() => profileDisplayValue(roleTitleRaw.value));
const roleIsEmpty = computed(() => isProfileEmpty(roleTitleRaw.value));
const isActive = computed(() => Boolean(props.profile.is_active));

const seniorityLabel = computed(() => props.profile.seniority?.label ?? null);
const accountRoleLabel = computed(() => props.profile.account_role?.label ?? null);
</script>

<template>
  <section
    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
    aria-label="Thông tin định danh"
  >
    <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-stretch sm:gap-6 sm:p-6">
      <!-- Avatar + mã NV — không lồng border/card -->
      <div class="flex w-full shrink-0 flex-col items-center gap-3 sm:w-[7.5rem]">
        <div class="relative">
          <Avatar
            :name="profile.name"
            :src="profile.avatar_path"
            :size="96"
            class="shadow-md shadow-slate-900/10"
          />
          <span
            class="absolute bottom-0.5 right-0.5 h-3.5 w-3.5 rounded-full ring-2 ring-white"
            :class="isActive ? 'bg-emerald-500' : 'bg-slate-400'"
            :title="isActive ? 'Đang hoạt động' : 'Ngừng hoạt động'"
          />
        </div>
        <span
          class="inline-flex max-w-full items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-medium tabular-nums text-slate-700"
          :title="profile.code"
        >
          <AppIcon
            name="account"
            :size="12"
            class="shrink-0 text-slate-400"
          />
          <span class="truncate">{{ profile.code }}</span>
        </span>
      </div>

      <!-- Identity — 2 cột -->
      <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
        <!-- Cột 1: tên & chức danh -->
        <div class="min-w-0 space-y-4 border-b border-slate-100 pb-4 sm:border-b-0 sm:border-r sm:pb-0 sm:pr-6">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Họ và tên
            </p>
            <h1 class="mt-1 font-display text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
              {{ profile.name }}
            </h1>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Chức danh
            </p>
            <p
              class="mt-1 text-sm leading-snug sm:text-[15px]"
              :class="roleIsEmpty ? 'italic text-slate-400' : 'font-medium text-slate-700'"
            >
              {{ roleTitle }}
            </p>
          </div>
        </div>

        <!-- Cột 2: trạng thái & quyền -->
        <div class="min-w-0 space-y-4">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Trạng thái
            </p>
            <p class="mt-1.5">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
                :class="isActive
                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/80'
                  : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200/80'"
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="isActive ? 'bg-emerald-500' : 'bg-slate-400'"
                />
                {{ isActive ? 'Đang làm việc' : 'Đã nghỉ' }}
              </span>
            </p>
          </div>

          <div v-if="seniorityLabel">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Cấp bậc
            </p>
            <p class="mt-1 text-sm font-medium text-slate-700">
              {{ seniorityLabel }}
            </p>
          </div>

          <div v-if="accountRoleLabel">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Quyền trên QLDA
            </p>
            <p class="mt-1.5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-700">
              <AppIcon
                name="shield"
                :size="14"
                class="text-slate-400"
              />
              {{ accountRoleLabel }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
