<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { prefersReducedMotionNow } from '@/modules/onboarding/composables/motion';

const props = defineProps({
    welcome: { type: Object, required: true },
    showActions: { type: Boolean, default: true },
});

const emit = defineEmits(['dismiss', 'skip']);

const reduced = prefersReducedMotionNow();

const extraCoworkers = computed(() => {
    const total = props.welcome?.coworker_total ?? 0;
    const shown = props.welcome?.coworkers?.length ?? 0;
    return Math.max(0, total - shown);
});

const employeeName = computed(() => props.welcome?.employee_name || 'bạn');
</script>

<template>
  <div class="welcome-panel relative overflow-hidden rounded-[1.5rem] bg-white shadow-elevation-3 ring-1 ring-slate-200/80">
    <button
      v-if="showActions"
      type="button"
      class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-slate-500 shadow-sm backdrop-blur transition-colors hover:bg-white hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40"
      aria-label="Đóng màn hình chào mừng"
      @click="emit('dismiss')"
    >
      <AppIcon
        name="close"
        :size="18"
      />
    </button>

    <div class="grid grid-cols-1 md:grid-cols-[minmax(15.5rem,18rem)_minmax(0,1fr)]">
      <div class="relative flex flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-700 px-6 py-8 text-center md:min-h-[20rem] md:py-10">
        <div
          class="pointer-events-none absolute inset-0"
          aria-hidden="true"
        >
          <div class="absolute -left-10 -top-12 h-40 w-40 rounded-full bg-white/20 blur-3xl" />
          <div class="absolute -right-12 top-6 h-44 w-44 rounded-full bg-rose-300/25 blur-3xl" />
          <div
            class="absolute inset-0 opacity-[0.12]"
            style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;"
          />
        </div>

        <img
          src="/images/congnghe/brand/vas-mascot-wave.png"
          alt="Linh vật VAS vẫy tay chào mừng"
          class="relative h-24 object-contain drop-shadow-[0_12px_24px_rgba(154,0,54,0.35)] sm:h-28"
          :class="reduced ? '' : 'animate-cn-float'"
          decoding="async"
        >

        <span class="relative mt-4 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/95 ring-1 ring-white/20 backdrop-blur-sm">
          <AppIcon
            name="sparkles"
            :size="13"
          />
          Chào mừng
        </span>

        <h1 class="relative mt-3 font-display text-2xl font-bold tracking-tight text-white sm:text-[26px]">
          Xin chào, {{ employeeName }}!
        </h1>
      </div>

      <div class="flex min-w-0 flex-col justify-center gap-5 px-6 py-6 sm:px-8 md:py-8">
        <div
          v-if="welcome.department || welcome.role_label"
          class="flex flex-wrap items-center gap-2"
        >
          <Badge
            v-if="welcome.department"
            :label="welcome.department.name"
            :color="welcome.department.color"
          />
          <Badge
            v-if="welcome.role_label"
            :label="welcome.role_label"
            color="slate"
          />
        </div>

        <div
          v-if="welcome.coworkers?.length"
          class="flex flex-wrap items-center gap-3"
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
            <span class="max-w-[72px] truncate text-[10.5px] text-slate-500">{{ c.name }}</span>
          </div>
          <div
            v-if="extraCoworkers > 0"
            class="grid h-10 w-10 place-items-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200/80"
          >
            +{{ extraCoworkers }}
          </div>
        </div>

        <div
          v-if="showActions"
          class="flex flex-wrap items-center gap-2"
        >
          <button
            type="button"
            class="btn-primary h-9 justify-center px-4 text-sm"
            @click="emit('dismiss')"
          >
            <AppIcon
              name="rocket"
              :size="16"
            />
            Bắt đầu làm việc
          </button>
          <button
            type="button"
            class="btn-ghost h-9 justify-center px-4 text-sm"
            @click="emit('skip')"
          >
            Bỏ qua
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
