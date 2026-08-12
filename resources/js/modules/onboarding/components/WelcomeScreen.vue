<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { useOnboardingWelcome } from '@/modules/onboarding/composables/useOnboardingWelcome';
import { prefersReducedMotionNow } from '@/modules/onboarding/composables/motion';

const { welcome, visible, markSeen } = useOnboardingWelcome();

const reduced = prefersReducedMotionNow();

const extraCoworkers = computed(() => {
    const total = welcome.value?.coworker_total ?? 0;
    const shown = welcome.value?.coworkers?.length ?? 0;
    return Math.max(0, total - shown);
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible && welcome"
        class="fixed inset-0 z-[65] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label="Màn hình chào mừng"
        @keydown.esc="markSeen"
      >
        <Transition
          appear
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="opacity-0 scale-95"
        >
          <div class="card relative my-8 w-full max-w-lg overflow-hidden p-0 shadow-elevation-3">
            <!-- Nút đóng -->
            <button
              type="button"
              class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/80 text-slate-500 backdrop-blur transition-colors hover:bg-white hover:text-slate-700"
              aria-label="Đóng màn hình chào mừng"
              @click="markSeen"
            >
              <AppIcon
                name="close"
                :size="18"
              />
            </button>

            <!-- Header brand -->
            <div class="relative overflow-hidden bg-gradient-to-br from-brand to-brand-700 px-6 pb-16 pt-8 text-center">
              <div
                class="pointer-events-none absolute inset-0 opacity-20"
                aria-hidden="true"
              >
                <div class="absolute -left-8 -top-8 h-32 w-32 rounded-full bg-white/40 blur-2xl" />
                <div class="absolute -right-10 top-10 h-40 w-40 rounded-full bg-white/30 blur-2xl" />
              </div>

              <span class="relative inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white/90">
                <AppIcon
                  name="sparkles"
                  :size="13"
                />
                Chào mừng
              </span>

              <h1 class="relative mt-3 font-display text-2xl font-bold text-white sm:text-[26px]">
                Xin chào, {{ welcome.employee_name }}!
              </h1>
              <p class="relative mt-1.5 text-sm text-white/85">
                Rất vui được đồng hành cùng bạn tại VAschools Workspace.
              </p>
            </div>

            <!-- Mascot nổi trên header -->
            <div class="relative -mt-14 flex justify-center">
              <img
                src="/images/congnghe/brand/vas-mascot-wave.png"
                alt="Linh vật VAS vẫy tay chào mừng"
                class="h-28 w-auto object-contain drop-shadow-[0_10px_20px_rgba(154,0,54,0.35)]"
                :class="reduced ? '' : 'animate-cn-float'"
                decoding="async"
              >
            </div>

            <!-- Nội dung -->
            <div class="px-6 pb-6 pt-3 text-center">
              <div class="flex flex-wrap items-center justify-center gap-2">
                <Badge
                  v-if="welcome.department"
                  :label="welcome.department.name"
                  :color="welcome.department.color"
                />
                <Badge
                  :label="welcome.role_label"
                  color="slate"
                />
              </div>

              <p
                v-if="welcome.department"
                class="mt-3 text-[13px] leading-relaxed text-slate-500"
              >
                Bạn đang thuộc <span class="font-semibold text-slate-700">{{ welcome.department.name }}</span>.
                Đây là những đồng nghiệp cùng phòng ban với bạn:
              </p>
              <p
                v-else
                class="mt-3 text-[13px] leading-relaxed text-slate-500"
              >
                Bạn chưa được gán vào phòng ban nào — liên hệ quản trị viên nếu đây là nhầm lẫn.
              </p>

              <!-- Đồng nghiệp cùng phòng -->
              <div
                v-if="welcome.coworkers?.length"
                class="mt-4 flex flex-wrap items-center justify-center gap-2"
              >
                <div
                  v-for="c in welcome.coworkers"
                  :key="c.id"
                  class="flex flex-col items-center gap-1"
                  :title="c.name"
                >
                  <Avatar
                    :name="c.name"
                    :src="c.avatar"
                    :size="40"
                  />
                  <span class="max-w-[64px] truncate text-[10.5px] text-slate-500">{{ c.name }}</span>
                </div>
                <div
                  v-if="extraCoworkers > 0"
                  class="grid h-10 w-10 place-items-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500"
                >
                  +{{ extraCoworkers }}
                </div>
              </div>

              <!-- Hành động -->
              <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <button
                  type="button"
                  class="btn-primary justify-center"
                  @click="markSeen"
                >
                  <AppIcon
                    name="rocket"
                    :size="16"
                  />
                  Bắt đầu làm việc
                </button>
                <button
                  type="button"
                  class="btn-ghost justify-center"
                  @click="markSeen"
                >
                  Bỏ qua
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
