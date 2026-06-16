<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import SectionHeading from './SectionHeading.vue';
import CountStat from './CountStat.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CongngheOrgChart from './CongngheOrgChart.vue';
import {
    formatCongngheMemberRoleTitle,
    shouldShowMemberSectionChip,
} from './useCongngheOrgLayout.js';
import ScanLineOverlay from './ScanLineOverlay.vue';
import { useInView } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    overview: { type: Object, default: () => ({}) },
    forest: { type: Array, default: () => [] },
    people: { type: Object, default: () => ({}) },
});

const roots = computed(() => props.forest ?? []);
const peopleTotal = computed(() => Number(props.overview?.people_total ?? 0));
const heading = computed(() => props.content?.heading ?? {});
const statLabel = computed(() => props.content?.stat_label ?? 'Nhân sự trên sơ đồ');

const { target, shown: sectionVisible } = useInView({ threshold: 0.18 });

const activeMember = ref(null);

function lookup(id) {
    if (id == null) return null;
    return props.people?.[id] ?? props.people?.[String(id)] ?? null;
}

function buildMember(id, fallback = {}) {
    const dir = lookup(id) ?? {};

    return {
        id,
        name: dir.name ?? fallback.name ?? 'Thành viên',
        code: dir.code ?? null,
        avatar: dir.avatar ?? fallback.avatar ?? null,
        role_title: dir.role_title ?? fallback.role_title ?? null,
        email: dir.email ?? fallback.email ?? null,
        phone: dir.phone ?? null,
        bio: dir.bio ?? null,
        team: dir.team ?? fallback.team ?? null,
        section: dir.section ?? fallback.section ?? null,
        is_leader: dir.is_leader ?? fallback.is_leader ?? false,
        is_active: dir.is_active ?? fallback.is_active ?? true,
    };
}

function onSelectPerson(person) {
    if (!person) return;
    activeMember.value = buildMember(person.employeeId, {
        name: person.name,
        avatar: person.avatar,
        role_title: person.roleTitle,
        email: person.email,
        section: person.isLeader ? null : (person.sectionTitle ?? null),
        team: person.teamName,
        is_active: person.isActive,
        is_leader: person.isLeader ?? false,
    });
}

function close() {
    activeMember.value = null;
}

const hasContact = computed(() => Boolean(activeMember.value?.email || activeMember.value?.phone));

const activeMemberRoleDisplay = computed(() => {
    const m = activeMember.value;
    if (!m?.role_title) {
        return null;
    }
    return formatCongngheMemberRoleTitle(m.role_title);
});

const showMemberSectionChip = computed(() => {
    const m = activeMember.value;
    if (!m) {
        return false;
    }
    return shouldShowMemberSectionChip(m.section, Boolean(m.is_leader));
});

function onKey(e) {
    if (e.key === 'Escape') close();
}

watch(activeMember, (m) => {
    if (typeof document === 'undefined') return;
    if (m) {
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
    } else {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKey);
    }
});
</script>

<template>
  <section
    id="to-chuc"
    ref="target"
    class="relative scroll-mt-24 py-12 sm:scroll-mt-28 sm:py-16 md:py-20"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/35 to-transparent"
      aria-hidden="true"
    />

    <div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
      <div
        v-if="roots.length"
        class="flex flex-col gap-6 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between md:gap-8"
      >
        <SectionHeading
          class="min-w-0 max-w-3xl"
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />

        <div class="inline-flex shrink-0 items-center gap-3 rounded-xl border border-white/12 bg-white/[0.06] px-5 py-3.5 text-left backdrop-blur">
          <span class="relative grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand to-[#ff4d8d] text-white shadow-md shadow-brand/25">
            <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            ><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle
              cx="9"
              cy="7"
              r="4"
            /><path d="M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75" /></svg>
            <span class="absolute inset-0 rounded-lg ring-2 ring-brand/40 animate-cn-ping-ring" />
          </span>
          <div>
            <p class="font-display text-2xl font-extrabold leading-none text-white sm:text-3xl">
              <CountStat
                :value="peopleTotal"
                :active="sectionVisible"
              />
            </p>
            <p class="mt-1 font-mono text-[10px] uppercase tracking-wider text-white/55">
              {{ statLabel }}
            </p>
          </div>
        </div>
      </div>

      <template v-else>
        <SectionHeading
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />
        <p class="mt-8 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45">
          Sơ đồ tổ chức chưa được thiết lập.
        </p>
      </template>
    </div>

    <div
      v-if="roots.length"
      class="cn-org-chart-bleed mx-auto mt-8 min-w-0 w-full px-3 sm:mt-10 sm:px-5 md:px-6 lg:px-8"
    >
      <CongngheOrgChart
        :trees="roots"
        :revealed="sectionVisible"
        @select-person="onSelectPerson"
      />
    </div>

    <Teleport to="body">
      <Transition name="cn-modal">
        <div
          v-if="activeMember"
          class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-6"
        >
          <div
            class="absolute inset-0 bg-[#05060c]/80 backdrop-blur-md"
            @click="close"
          />

          <div
            class="cn-modal-panel relative w-full max-w-md overflow-hidden rounded-3xl border border-white/12 bg-[#0d0e16]/95 shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)]"
            role="dialog"
            aria-modal="true"
          >
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-[radial-gradient(80%_120%_at_50%_-20%,rgba(255,77,141,0.35),transparent_70%)]" />
            <ScanLineOverlay
              trigger="always"
              tone="brand"
            />

            <button
              type="button"
              class="absolute right-4 top-4 z-10 grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-white/60 transition hover:border-white/25 hover:bg-white/10 hover:text-white"
              aria-label="Đóng"
              @click="close"
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>

            <div class="relative px-7 pb-7 pt-9">
              <div class="flex flex-col items-center text-center">
                <span class="relative">
                  <span class="block rounded-full p-[3px] ring-2 ring-brand/40">
                    <Avatar
                      :name="activeMember.name"
                      :src="activeMember.avatar"
                      :size="92"
                      img-alt=""
                    />
                  </span>
                  <span
                    class="absolute bottom-1.5 right-1.5 h-4 w-4 rounded-full border-2 border-[#0d0e16]"
                    :class="activeMember.is_active ? 'bg-emerald-400' : 'bg-slate-400'"
                    :title="activeMember.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động'"
                  />
                </span>

                <h3 class="mt-4 font-display text-xl font-bold text-white">
                  {{ activeMember.name }}
                </h3>
                <p
                  v-if="activeMemberRoleDisplay"
                  class="mt-1 text-sm text-white/60"
                >
                  {{ activeMemberRoleDisplay }}
                </p>

                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                  <span
                    v-if="activeMember.is_leader"
                    class="inline-flex items-center gap-1.5 rounded-full bg-brand/15 px-2.5 py-1 text-[11px] font-semibold text-brand-200 ring-1 ring-inset ring-brand/30"
                  >
                    <svg
                      width="11"
                      height="11"
                      viewBox="0 0 24 24"
                      fill="currentColor"
                    ><path d="m12 2 3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7Z" /></svg>
                    Quản lý
                  </span>
                  <span
                    v-if="activeMember.team"
                    class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-2.5 py-1 text-[11px] font-medium text-white/65 ring-1 ring-inset ring-white/10"
                  >
                    {{ activeMember.team }}
                  </span>
                  <span
                    v-if="showMemberSectionChip"
                    class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-2.5 py-1 text-[11px] font-medium text-white/65 ring-1 ring-inset ring-white/10"
                  >
                    {{ activeMember.section }}
                  </span>
                </div>
              </div>

              <div class="mt-6 rounded-2xl border border-white/8 bg-white/[0.03] p-4">
                <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-white/35">
                  Giới thiệu
                </p>
                <p class="mt-2 text-[13px] leading-relaxed text-white/65">
                  {{ activeMember.bio || 'Chưa có mô tả giới thiệu cho thành viên này.' }}
                </p>
              </div>

              <div class="mt-4 space-y-2.5">
                <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-white/35">
                  Liên lạc
                </p>
                <a
                  v-if="activeMember.email"
                  :href="`mailto:${activeMember.email}`"
                  class="flex items-center gap-3 rounded-xl border border-white/8 bg-white/[0.03] px-3.5 py-2.5 transition hover:border-brand/30 hover:bg-white/[0.06]"
                >
                  <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5 text-brand-200">
                    <svg
                      width="15"
                      height="15"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                    ><rect
                      x="2"
                      y="4"
                      width="20"
                      height="16"
                      rx="2"
                    /><path d="m22 7-10 6L2 7" /></svg>
                  </span>
                  <span class="min-w-0 flex-1 truncate text-[13px] text-white/75">{{ activeMember.email }}</span>
                </a>
                <a
                  v-if="activeMember.phone"
                  :href="`tel:${activeMember.phone}`"
                  class="flex items-center gap-3 rounded-xl border border-white/8 bg-white/[0.03] px-3.5 py-2.5 transition hover:border-brand/30 hover:bg-white/[0.06]"
                >
                  <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/5 text-brand-200">
                    <svg
                      width="15"
                      height="15"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                    ><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z" /></svg>
                  </span>
                  <span class="min-w-0 flex-1 truncate text-[13px] text-white/75">{{ activeMember.phone }}</span>
                </a>
                <p
                  v-if="!hasContact"
                  class="rounded-xl border border-dashed border-white/10 px-3.5 py-3 text-center text-[12.5px] text-white/40"
                >
                  Chưa cập nhật thông tin liên lạc.
                </p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

<style scoped>
/* Sơ đồ rộng hơn các section max-w-7xl; vẫn giữ lề an toàn trên viewport hẹp */
.cn-org-chart-bleed {
    max-width: min(100rem, calc(100vw - 1.5rem));
}

@media (min-width: 640px) {
    .cn-org-chart-bleed {
        max-width: min(100rem, calc(100vw - 2.5rem));
    }
}

.cn-modal-enter-active,
.cn-modal-leave-active {
    transition: opacity 0.25s ease;
}

.cn-modal-enter-from,
.cn-modal-leave-to {
    opacity: 0;
}

.cn-modal-enter-active .cn-modal-panel,
.cn-modal-leave-active .cn-modal-panel {
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s ease;
}

.cn-modal-enter-from .cn-modal-panel,
.cn-modal-leave-to .cn-modal-panel {
    transform: translateY(16px) scale(0.96);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .cn-modal-enter-active,
    .cn-modal-leave-active,
    .cn-modal-enter-active .cn-modal-panel,
    .cn-modal-leave-active .cn-modal-panel {
        transition: none;
    }
}
</style>
