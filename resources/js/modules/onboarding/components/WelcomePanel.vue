<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { prefersReducedMotionNow } from '@/modules/onboarding/composables/motion';

const props = defineProps({
    welcome: { type: Object, required: true },
    /** compact = thẻ xem trước trong /settings/onboarding */
    compact: { type: Boolean, default: false },
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
  <div
    class="welcome-panel relative overflow-hidden bg-white shadow-elevation-3"
    :class="compact
      ? 'rounded-2xl ring-1 ring-slate-200/80'
      : 'rounded-[1.75rem] ring-1 ring-white/40'"
  >
    <button
      v-if="showActions && !compact"
      type="button"
      class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/85 text-slate-500 shadow-sm backdrop-blur transition-colors hover:bg-white hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
      aria-label="Đóng màn hình chào mừng"
      @click="emit('dismiss')"
    >
      <AppIcon
        name="close"
        :size="18"
      />
    </button>

    <!-- Header brand -->
    <div
      class="relative overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-700 text-center"
      :class="compact ? 'px-5 pb-14 pt-6' : 'px-6 pb-16 pt-9 sm:px-8'"
    >
      <div
        class="pointer-events-none absolute inset-0"
        aria-hidden="true"
      >
        <div class="absolute -left-10 -top-12 h-40 w-40 rounded-full bg-white/20 blur-3xl" />
        <div class="absolute -right-12 top-6 h-44 w-44 rounded-full bg-rose-300/25 blur-3xl" />
        <div class="absolute bottom-0 left-1/2 h-24 w-[120%] -translate-x-1/2 bg-gradient-to-t from-black/10 to-transparent" />
        <div
          class="absolute inset-0 opacity-[0.12]"
          style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;"
        />
      </div>

      <span class="relative inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/95 ring-1 ring-white/20 backdrop-blur-sm">
        <AppIcon
          name="sparkles"
          :size="13"
        />
        Chào mừng
      </span>

      <h1
        class="relative mt-3 font-display font-bold tracking-tight text-white"
        :class="compact ? 'text-xl' : 'text-2xl sm:text-[28px]'"
      >
        Xin chào, {{ employeeName }}!
      </h1>
      <p
        class="relative mx-auto mt-2 max-w-sm text-white/85"
        :class="compact ? 'text-xs' : 'text-sm'"
      >
        Rất vui được đồng hành cùng bạn tại VAschools Workspace.
      </p>
    </div>

    <!-- Mascot -->
    <div class="relative -mt-12 flex justify-center sm:-mt-14">
      <div class="relative">
        <div
          class="absolute inset-0 -z-10 scale-110 rounded-full bg-brand/15 blur-xl"
          aria-hidden="true"
        />
        <img
          src="/images/congnghe/brand/vas-mascot-wave.png"
          alt="Linh vật VAS vẫy tay chào mừng"
          class="object-contain drop-shadow-[0_12px_24px_rgba(154,0,54,0.35)]"
          :class="[
            compact ? 'h-20' : 'h-28',
            reduced ? '' : 'animate-cn-float',
          ]"
          decoding="async"
        >
      </div>
    </div>

    <!-- Body -->
    <div
      class="text-center"
      :class="compact ? 'px-5 pb-5 pt-2' : 'px-6 pb-7 pt-3 sm:px-8'"
    >
      <div class="flex flex-wrap items-center justify-center gap-2">
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

      <p
        v-if="welcome.department"
        class="mt-3 leading-relaxed text-slate-500"
        :class="compact ? 'text-xs' : 'text-[13px]'"
      >
        Bạn đang thuộc <span class="font-semibold text-slate-700">{{ welcome.department.name }}</span>.
        Đây là những đồng nghiệp cùng phòng ban với bạn:
      </p>
      <p
        v-else
        class="mt-3 leading-relaxed text-slate-500"
        :class="compact ? 'text-xs' : 'text-[13px]'"
      >
        Bạn chưa được gán vào phòng ban nào — liên hệ quản trị viên nếu đây là nhầm lẫn.
      </p>

      <div
        v-if="welcome.coworkers?.length"
        class="mt-4 flex flex-wrap items-center justify-center gap-2.5"
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
            :size="compact ? 36 : 40"
          />
          <span class="max-w-[64px] truncate text-[10.5px] text-slate-500">{{ c.name }}</span>
        </div>
        <div
          v-if="extraCoworkers > 0"
          class="grid place-items-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200/80"
          :class="compact ? 'h-9 w-9' : 'h-10 w-10'"
        >
          +{{ extraCoworkers }}
        </div>
      </div>

      <div
        v-if="showActions"
        class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center"
      >
        <button
          type="button"
          class="btn-primary justify-center"
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
          class="btn-ghost justify-center"
          @click="emit('skip')"
        >
          Bỏ qua
        </button>
      </div>
    </div>
  </div>
</template>
