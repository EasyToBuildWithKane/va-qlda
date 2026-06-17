<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { ROLE_TOUR, tourTitle, tourEstMinutes } from '@/modules/onboarding/tours';

const props = defineProps({
    show: { type: Boolean, default: false },
    role: { type: String, default: 'member' },
    appName: { type: String, default: 'hệ thống' },
});

const emit = defineEmits(['start', 'dismiss']);

const roleTourKey = computed(() => ROLE_TOUR[props.role] || 'role_member');

const groups = [
    { icon: 'projects', label: 'Dự án & Công việc', desc: 'Sprint, Task, Kanban' },
    { icon: 'daily', label: 'Báo cáo ngày', desc: 'Cập nhật tiến độ' },
    { icon: 'performance', label: 'Hiệu suất & Audit', desc: 'KPI, đánh giá' },
    { icon: 'people', label: 'Tổ chức & Nhân sự', desc: 'Phòng ban, thành viên' },
];
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm"
      >
        <div class="card w-full max-w-xl overflow-hidden p-0 shadow-elevation-3">
          <!-- Header -->
          <div class="relative bg-brand px-7 py-8 text-white">
            <div class="absolute inset-0 opacity-20 [background:radial-gradient(circle_at_top_right,white,transparent_60%)]" />
            <div class="relative">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                <AppIcon
                  name="sparkles"
                  :size="13"
                /> Hướng dẫn tương tác
              </span>
              <h2 class="mt-3 font-display text-2xl font-bold leading-tight">
                Chào mừng đến với {{ appName }}!
              </h2>
              <p class="mt-1.5 max-w-md text-sm text-white/85">
                Cùng khám phá nhanh các chức năng chính để bạn bắt đầu làm việc tự tin chỉ trong vài phút.
              </p>
            </div>
          </div>

          <!-- Body -->
          <div class="px-7 py-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Các nhóm chức năng chính
            </p>
            <div class="mt-3 grid grid-cols-2 gap-3">
              <div
                v-for="g in groups"
                :key="g.label"
                class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3"
              >
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
                  <AppIcon
                    :name="g.icon"
                    :size="18"
                  />
                </span>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-slate-700">
                    {{ g.label }}
                  </p>
                  <p class="truncate text-xs text-slate-400">
                    {{ g.desc }}
                  </p>
                </div>
              </div>
            </div>

            <div class="mt-5 flex items-center gap-2 rounded-lg bg-amber-50 px-3.5 py-2.5 text-[13px] text-amber-700">
              <AppIcon
                name="clock"
                :size="15"
                class="shrink-0"
              />
              <span>Thời gian hoàn thành dự kiến: <strong>khoảng {{ tourEstMinutes('system_overview') }} phút</strong>.</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-2.5 border-t border-slate-100 bg-slate-50/50 px-7 py-5 sm:flex-row sm:items-center">
            <button
              type="button"
              class="btn-primary inline-flex flex-1 items-center justify-center gap-2"
              @click="emit('start', 'system_overview')"
            >
              <AppIcon
                name="rocket"
                :size="16"
              /> Bắt đầu hướng dẫn
            </button>
            <button
              type="button"
              class="inline-flex flex-1 items-center justify-center gap-2 rounded-btn border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50"
              @click="emit('start', roleTourKey)"
            >
              <AppIcon
                name="target"
                :size="16"
              /> {{ tourTitle(roleTourKey) }}
            </button>
            <button
              type="button"
              class="rounded-btn px-4 py-2.5 text-sm font-medium text-slate-400 transition-colors hover:text-slate-600"
              @click="emit('dismiss')"
            >
              Bỏ qua
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
