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

const roleTitle = computed(() => profileDisplayValue(props.profile.role_title));
const roleIsEmpty = computed(() => roleTitle.value === profileDisplayValue(null));
</script>

<template>
  <section
    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
    aria-label="Thông tin định danh"
  >
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
              <span
                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-medium"
                :class="profile.is_active
                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/80'
                  : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200/80'"
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="profile.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                />
                {{ profile.is_active ? 'Đang làm việc' : 'Đã nghỉ' }}
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
    </div>
  </section>
</template>
