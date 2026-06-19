<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    greeting: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});

const roleLabelMap = {
    super_admin: 'Siêu quản trị',
    admin: 'Quản trị viên',
    lead: 'Quản lý',
    member: 'Thành viên',
    viewer: 'Quan sát',
};

const roleLabel = computed(() => roleLabelMap[user.value.role] ?? user.value.role ?? '');

const roleRingColor = {
    super_admin: 'bg-rose-100 text-rose-700 ring-rose-300/60',
    admin: 'bg-brand/10 text-brand ring-brand/20',
    lead: 'bg-violet-100 text-violet-700 ring-violet-300/60',
    member: 'bg-sky-100 text-sky-700 ring-sky-300/60',
    viewer: 'bg-slate-100 text-slate-600 ring-slate-300/60',
};

const roleRing = computed(() => roleRingColor[user.value.role] ?? 'bg-slate-100 text-slate-600 ring-slate-300/60');

const displayName = computed(() => {
    if (props.greeting?.name) {
        return props.greeting.name;
    }
    const u = user.value;
    return u.display_name || u.employee?.full_name || u.username || 'bạn';
});

const avatarSrc = computed(() => user.value.employee?.avatar_path ?? null);
const appShort = computed(() => page.props.app?.short_name ?? 'VA QLDA');
</script>

<template>
  <section
    class="hub-welcome relative mb-5 overflow-hidden rounded-card border border-slate-200/80 shadow-sm"
    aria-label="Lời chào và lối tắt"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand/[0.08] via-white to-slate-50"
      aria-hidden="true"
    />
    <div
      class="kpi-strip__bg-outer pointer-events-none absolute -right-8 top-0 h-full w-[55%] bg-gradient-to-l from-brand/[0.06] to-transparent"
      aria-hidden="true"
    />
    <div
      class="kpi-strip__bg-inner pointer-events-none absolute -right-4 bottom-0 h-[70%] w-[40%] bg-gradient-to-tl from-slate-100/80 to-transparent"
      aria-hidden="true"
    />

    <div class="relative flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-start gap-4">
        <Avatar
          :name="displayName"
          :src="avatarSrc"
          :size="52"
          class="ring-2 ring-white shadow-md"
        />
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">
            {{ greeting.date }}
          </p>
          <h2 class="mt-1 font-display text-xl font-semibold leading-snug text-slate-800 sm:text-[1.35rem]">
            {{ greeting.text }},
            <span class="text-brand">{{ displayName }}</span>
          </h2>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
              :class="roleRing"
            >
              {{ roleLabel }}
            </span>
            <span class="text-[11px] text-slate-500">
              {{ appShort }} · Trung tâm điều hành
            </span>
          </div>
        </div>
      </div>

      <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-stretch lg:flex-row">
        <Link
          :href="route('work-dashboard')"
          class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <AppIcon
            name="board"
            :size="15"
            class="text-white/90"
          />
          Dashboard công việc
          <AppIcon
            name="chevron-right"
            :size="14"
            class="text-white/70"
          />
        </Link>
        <Link
          href="/projects"
          class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200/90 bg-white/90 px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur-sm transition hover:border-brand/25 hover:bg-white hover:text-brand"
        >
          <AppIcon
            name="all-projects"
            :size="15"
            class="text-brand"
          />
          Danh sách dự án
        </Link>
      </div>
    </div>
  </section>
</template>

<style scoped>
@import '@/shared/styles/kpi-summary-strip.css';
</style>
