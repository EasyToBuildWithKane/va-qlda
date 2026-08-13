<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { prefersReducedMotionNow } from '@/modules/onboarding/composables/motion';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const ROLE_LABELS_VI = {
    super_admin: 'Quản trị tối cao',
    admin: 'Quản trị viên',
    lead: 'Trưởng nhóm',
    member: 'Thành viên',
    viewer: 'Người xem',
};

const START_HINTS = [
    { icon: 'daily', title: 'Báo cáo ngày', text: 'Ghi lại việc đã làm, vướng mắc và kế hoạch ngày mai.' },
    { icon: 'projects', title: 'Dự án', text: 'Xem việc được giao, sprint đang chạy và tiến độ nhóm.' },
    { icon: 'account', title: 'Hồ sơ', text: 'Kiểm tra thông tin cá nhân, đơn vị và vai trò của bạn.' },
];

const props = defineProps({
    welcome: { type: Object, required: true },
    showActions: { type: Boolean, default: true },
});

const emit = defineEmits(['dismiss', 'skip']);

const reduced = prefersReducedMotionNow();

const employeeName = computed(() => props.welcome?.employee_name || 'bạn');

const timeGreeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Chào buổi sáng';
    if (hour < 18) return 'Chào buổi chiều';
    return 'Chào buổi tối';
});

const roleLabel = computed(() => {
    const fromRole = ROLE_LABELS_VI[props.welcome?.role];
    if (fromRole) return fromRole;
    return displayOrEmpty(props.welcome?.role_label, EMPTY_LABELS.role);
});

const departmentName = computed(() =>
    displayOrEmpty(props.welcome?.department?.name, EMPTY_LABELS.team),
);

const jobTitle = computed(() => {
    const title = props.welcome?.role_title;
    return title && String(title).trim() ? String(title).trim() : '';
});

const extraCoworkers = computed(() => {
    const total = props.welcome?.coworker_total ?? 0;
    const shown = props.welcome?.coworkers?.length ?? 0;
    return Math.max(0, total - shown);
});

const coworkerTotal = computed(() => props.welcome?.coworker_total ?? 0);

const intro = computed(() => {
    const dept = props.welcome?.department?.name;
    const title = jobTitle.value;
    const role = roleLabel.value;

    if (dept && title) {
        return `Rất vui được chào đón bạn trên VA Workspace — không gian quản lý dự án và cộng tác của VAschools. Bạn đang làm việc tại ${dept} với chức danh ${title}, vai trò ${role}.`;
    }
    if (dept) {
        return `Rất vui được chào đón bạn trên VA Workspace — không gian quản lý dự án và cộng tác của VAschools. Bạn đang làm việc tại ${dept} với vai trò ${role}.`;
    }
    return `Rất vui được chào đón bạn trên VA Workspace — không gian quản lý dự án và cộng tác của VAschools. Vai trò của bạn trên hệ thống là ${role}.`;
});

const teamHeading = computed(() => {
    if (coworkerTotal.value <= 0) return 'Đồng nghiệp cùng đơn vị';
    return `Đồng nghiệp cùng đơn vị · ${coworkerTotal.value} người`;
});
</script>

<template>
  <div class="welcome-panel relative overflow-hidden rounded-[1.75rem] bg-white shadow-elevation-3 ring-1 ring-slate-200/80">
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

    <header class="relative overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-700 px-6 py-7 sm:px-8 sm:py-8">
      <div
        class="pointer-events-none absolute inset-0"
        aria-hidden="true"
      >
        <div class="absolute -left-10 -top-12 h-40 w-40 rounded-full bg-white/20 blur-3xl" />
        <div class="absolute -right-12 top-4 h-48 w-48 rounded-full bg-rose-300/25 blur-3xl" />
        <div class="absolute bottom-0 left-1/3 h-24 w-56 rounded-full bg-white/10 blur-2xl" />
        <div
          class="absolute inset-0 opacity-[0.12]"
          style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;"
        />
      </div>

      <div class="relative flex items-center gap-4 sm:gap-5">
        <img
          src="/images/congnghe/brand/vas-mascot-wave.png"
          alt="Linh vật VAS vẫy tay chào mừng"
          class="h-[4.5rem] w-auto shrink-0 object-contain drop-shadow-[0_12px_24px_rgba(154,0,54,0.35)] sm:h-24"
          :class="reduced ? '' : 'animate-cn-float'"
          decoding="async"
        >

        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-white/80">
            {{ timeGreeting }}
          </p>
          <h1 class="mt-1 font-display text-[1.65rem] font-bold leading-tight tracking-tight text-white break-words sm:text-3xl">
            {{ employeeName }}
          </h1>
          <p class="mt-1.5 text-sm leading-snug text-white/80">
            VA Workspace · Không gian làm việc VAschools
          </p>
        </div>

        <span class="hidden shrink-0 rounded-full ring-2 ring-white/40 sm:inline-flex">
          <Avatar
            :name="employeeName"
            :src="welcome.employee_avatar"
            :size="56"
          />
        </span>
      </div>
    </header>

    <div class="flex flex-col gap-5 px-6 py-6 sm:px-8 sm:py-7">
      <p class="text-sm leading-relaxed text-slate-600">
        {{ intro }}
      </p>

      <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
          <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            <AppIcon
              name="department"
              :size="13"
            />
            Phòng ban
          </dt>
          <dd class="mt-1.5 text-sm font-semibold leading-snug text-slate-800 break-words">
            {{ departmentName }}
          </dd>
        </div>
        <div class="rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
          <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            <AppIcon
              name="briefcase"
              :size="13"
            />
            Vai trò
          </dt>
          <dd class="mt-1.5 text-sm font-semibold leading-snug text-slate-800 break-words">
            {{ roleLabel }}
            <span
              v-if="jobTitle"
              class="mt-0.5 block text-xs font-medium text-slate-500 break-words"
            >{{ jobTitle }}</span>
          </dd>
        </div>
      </dl>

      <section aria-labelledby="welcome-team-heading">
        <h2
          id="welcome-team-heading"
          class="text-sm font-semibold text-slate-800"
        >
          {{ teamHeading }}
        </h2>

        <ul
          v-if="welcome.coworkers?.length"
          class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2"
        >
          <li
            v-for="person in welcome.coworkers"
            :key="person.id"
            class="flex items-start gap-2.5 rounded-xl px-1 py-0.5"
          >
            <Avatar
              :name="person.name"
              :src="person.avatar"
              :size="36"
            />
            <div class="min-w-0 pt-0.5">
              <p class="text-sm font-medium leading-snug text-slate-800 break-words">
                {{ person.name }}
              </p>
              <p
                v-if="person.role_title"
                class="mt-0.5 text-xs leading-snug text-slate-500 break-words"
              >
                {{ person.role_title }}
              </p>
            </div>
          </li>
        </ul>

        <p
          v-else
          class="mt-2 text-sm leading-relaxed text-slate-500"
        >
          Chưa có đồng nghiệp cùng đơn vị trên hệ thống.
        </p>

        <p
          v-if="extraCoworkers > 0"
          class="mt-2 text-xs leading-relaxed text-slate-500"
        >
          và {{ extraCoworkers }} người khác trong cùng đơn vị
        </p>
      </section>

      <section aria-labelledby="welcome-start-heading">
        <h2
          id="welcome-start-heading"
          class="text-sm font-semibold text-slate-800"
        >
          Bạn có thể bắt đầu với
        </h2>
        <ol class="mt-3 space-y-2.5">
          <li
            v-for="hint in START_HINTS"
            :key="hint.title"
            class="flex items-start gap-3"
          >
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand ring-1 ring-brand/10">
              <AppIcon
                :name="hint.icon"
                :size="15"
              />
            </span>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-800">
                {{ hint.title }}
              </p>
              <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
                {{ hint.text }}
              </p>
            </div>
          </li>
        </ol>
      </section>

      <div
        v-if="showActions"
        class="flex flex-wrap items-center gap-2 pt-1"
      >
        <button
          type="button"
          class="btn-primary h-10 justify-center px-5 text-sm"
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
          class="btn-ghost h-10 justify-center px-4 text-sm"
          @click="emit('skip')"
        >
          Để sau
        </button>
      </div>
    </div>
  </div>
</template>
