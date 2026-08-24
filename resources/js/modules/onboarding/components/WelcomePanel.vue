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
    /** stack = overlay mặc định; horizontal = preview settings — hai cột, gọn theo chiều cao */
    layout: {
        type: String,
        default: 'stack',
        validator: (v) => ['stack', 'horizontal'].includes(v),
    },
});

const isHorizontal = computed(() => props.layout === 'horizontal');

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

const displayCoworkers = computed(() => {
    const list = props.welcome?.coworkers ?? [];
    if (!isHorizontal.value) return list;
    return list.slice(0, 4);
});
</script>

<template>
  <div
    class="welcome-panel relative overflow-hidden rounded-[1.75rem] bg-white shadow-elevation-3 ring-1 ring-slate-200/80"
    :class="isHorizontal ? 'flex h-full min-h-0 flex-col md:flex-row' : ''"
  >
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

    <header
      class="relative shrink-0 overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-700"
      :class="isHorizontal
        ? 'px-5 py-5 md:flex md:w-[34%] md:max-w-[17rem] md:flex-col md:justify-center md:py-6 lg:max-w-[19rem]'
        : 'px-6 py-7 sm:px-8 sm:py-8'"
    >
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

      <div
        class="relative flex items-center gap-4 sm:gap-5"
        :class="isHorizontal ? 'md:flex-col md:items-start md:gap-3' : ''"
      >
        <img
          src="/images/congnghe/brand/vas-mascot-wave.png"
          alt="Linh vật VAS vẫy tay chào mừng"
          class="h-[4.5rem] w-auto shrink-0 object-contain drop-shadow-[0_12px_24px_rgba(154,0,54,0.35)] sm:h-24"
          :class="[
            reduced ? '' : 'animate-cn-float',
            isHorizontal ? 'md:h-20 md:w-auto lg:h-[4.5rem]' : '',
          ]"
          decoding="async"
        >

        <div class="min-w-0 flex-1">
          <p
            class="font-medium text-white/80"
            :class="isHorizontal ? 'text-xs md:text-sm' : 'text-sm'"
          >
            {{ timeGreeting }}
          </p>
          <h1
            class="mt-1 font-display font-bold leading-tight tracking-tight text-white break-words"
            :class="isHorizontal
              ? 'text-xl sm:text-2xl md:text-[1.35rem] lg:text-2xl'
              : 'text-[1.65rem] sm:text-3xl'"
          >
            {{ employeeName }}
          </h1>
          <p
            class="mt-1.5 leading-snug text-white/80"
            :class="isHorizontal ? 'text-xs md:text-[11px] lg:text-xs' : 'text-sm'"
          >
            VA Workspace · Không gian làm việc VAschools
          </p>
        </div>

        <span
          class="hidden shrink-0 rounded-full ring-2 ring-white/40 sm:inline-flex"
          :class="isHorizontal ? 'md:mt-1' : ''"
        >
          <Avatar
            :name="employeeName"
            :src="welcome.employee_avatar"
            :size="isHorizontal ? 48 : 56"
          />
        </span>
      </div>
    </header>

    <div
      class="flex min-h-0 min-w-0 flex-col"
      :class="isHorizontal
        ? 'flex-1 justify-center gap-3 overflow-hidden px-4 py-4 sm:px-5 md:py-5 lg:gap-3.5'
        : 'gap-5 px-6 py-6 sm:px-8 sm:py-7'"
    >
      <p
        class="leading-relaxed text-slate-600"
        :class="isHorizontal ? 'line-clamp-3 text-xs lg:text-[13px]' : 'text-sm'"
      >
        {{ intro }}
      </p>

      <dl
        class="grid shrink-0 gap-3"
        :class="isHorizontal ? 'grid-cols-2 gap-2' : 'grid-cols-1 sm:grid-cols-2'"
      >
        <div
          class="rounded-xl bg-slate-50 ring-1 ring-slate-100"
          :class="isHorizontal ? 'px-3 py-2' : 'px-4 py-3'"
        >
          <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            <AppIcon
              name="department"
              :size="13"
            />
            Phòng ban
          </dt>
          <dd
            class="mt-1 font-semibold leading-snug text-slate-800 break-words"
            :class="isHorizontal ? 'text-xs line-clamp-2' : 'mt-1.5 text-sm'"
          >
            {{ departmentName }}
          </dd>
        </div>
        <div
          class="rounded-xl bg-slate-50 ring-1 ring-slate-100"
          :class="isHorizontal ? 'px-3 py-2' : 'px-4 py-3'"
        >
          <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            <AppIcon
              name="briefcase"
              :size="13"
            />
            Vai trò
          </dt>
          <dd
            class="mt-1 font-semibold leading-snug text-slate-800 break-words"
            :class="isHorizontal ? 'text-xs' : 'mt-1.5 text-sm'"
          >
            {{ roleLabel }}
            <span
              v-if="jobTitle"
              class="mt-0.5 block font-medium text-slate-500 break-words"
              :class="isHorizontal ? 'text-[10px] line-clamp-1' : 'text-xs'"
            >{{ jobTitle }}</span>
          </dd>
        </div>
      </dl>

      <section
        aria-labelledby="welcome-team-heading"
        :class="isHorizontal ? 'min-h-0 shrink' : ''"
      >
        <h2
          id="welcome-team-heading"
          class="font-semibold text-slate-800"
          :class="isHorizontal ? 'text-xs' : 'text-sm'"
        >
          {{ teamHeading }}
        </h2>

        <ul
          v-if="displayCoworkers.length"
          class="mt-2 grid gap-2"
          :class="isHorizontal ? 'grid-cols-2 lg:grid-cols-4' : 'mt-3 grid-cols-1 sm:grid-cols-2'"
        >
          <li
            v-for="person in displayCoworkers"
            :key="person.id"
            class="flex min-w-0 items-center gap-2 rounded-lg px-0.5"
            :class="isHorizontal ? '' : 'items-start gap-2.5 rounded-xl px-1 py-0.5'"
          >
            <Avatar
              :name="person.name"
              :src="person.avatar"
              :size="isHorizontal ? 28 : 36"
            />
            <div class="min-w-0 pt-0.5">
              <p
                class="font-medium leading-snug text-slate-800 break-words"
                :class="isHorizontal ? 'text-[11px] line-clamp-1' : 'text-sm'"
              >
                {{ person.name }}
              </p>
              <p
                v-if="person.role_title && !isHorizontal"
                class="mt-0.5 text-xs leading-snug text-slate-500 break-words"
              >
                {{ person.role_title }}
              </p>
            </div>
          </li>
        </ul>

        <p
          v-else
          class="mt-1.5 leading-relaxed text-slate-500"
          :class="isHorizontal ? 'text-[11px] line-clamp-2' : 'mt-2 text-sm'"
        >
          Chưa có đồng nghiệp cùng đơn vị trên hệ thống.
        </p>

        <p
          v-if="extraCoworkers > 0"
          class="mt-1 text-xs leading-relaxed text-slate-500"
          :class="isHorizontal ? 'text-[10px]' : 'mt-2'"
        >
          và {{ extraCoworkers }} người khác trong cùng đơn vị
        </p>
      </section>

      <section
        aria-labelledby="welcome-start-heading"
        class="shrink-0"
      >
        <h2
          id="welcome-start-heading"
          class="font-semibold text-slate-800"
          :class="isHorizontal ? 'text-xs' : 'text-sm'"
        >
          Bạn có thể bắt đầu với
        </h2>
        <ol
          class="mt-2"
          :class="isHorizontal
            ? 'grid grid-cols-3 gap-2'
            : 'mt-3 space-y-2.5'"
        >
          <li
            v-for="hint in START_HINTS"
            :key="hint.title"
            class="flex min-w-0 items-start gap-2"
            :class="isHorizontal ? 'flex-col gap-1.5' : 'gap-3'"
          >
            <span
              class="grid shrink-0 place-items-center rounded-lg bg-brand/10 text-brand ring-1 ring-brand/10"
              :class="isHorizontal ? 'h-7 w-7' : 'mt-0.5 h-8 w-8'"
            >
              <AppIcon
                :name="hint.icon"
                :size="isHorizontal ? 13 : 15"
              />
            </span>
            <div class="min-w-0">
              <p
                class="font-semibold text-slate-800"
                :class="isHorizontal ? 'text-[11px] leading-tight' : 'text-sm'"
              >
                {{ hint.title }}
              </p>
              <p
                v-if="!isHorizontal"
                class="mt-0.5 text-xs leading-relaxed text-slate-500"
              >
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
