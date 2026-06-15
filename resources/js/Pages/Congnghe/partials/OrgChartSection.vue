<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SectionHeading from './SectionHeading.vue';
import PersonAvatar from './PersonAvatar.vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    forest: { type: Array, default: () => [] },
});

const roots = computed(() => props.forest ?? []);

const stats = computed(() => [
    { label: 'Thành viên', value: props.overview?.people_total ?? 0 },
    { label: 'Nhóm', value: props.overview?.teams_total ?? 0 },
    { label: 'Quản lý', value: props.overview?.leaders_total ?? 0 },
    { label: 'Đang hoạt động', value: `${props.overview?.active_ratio ?? 0}%` },
]);

function memberCount(node) {
    return Array.isArray(node?.members) ? node.members.length : 0;
}
</script>

<template>
  <section
    id="to-chuc"
    class="relative border-t border-white/5 py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Đội ngũ · Sơ đồ tổ chức"
        title="Cấu trúc vận hành"
        subtitle="Các nhóm chuyên môn phối hợp theo sơ đồ tổ chức thực tế của phòng."
      />

      <div class="mt-10 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div
          v-for="s in stats"
          :key="s.label"
          class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 text-center"
        >
          <p class="font-display text-2xl font-bold text-white sm:text-3xl">
            {{ s.value }}
          </p>
          <p class="mt-1 text-[12px] text-white/50">
            {{ s.label }}
          </p>
        </div>
      </div>

      <div
        v-if="roots.length"
        class="mt-8 grid gap-5 lg:grid-cols-2"
      >
        <article
          v-for="root in roots"
          :key="root.id"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-6"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <PersonAvatar
                :name="root.leader?.name"
                :src="root.leader?.avatar_path"
                size="md"
              />
              <div class="min-w-0">
                <h3 class="truncate font-display text-base font-bold text-white">
                  {{ root.name }}
                </h3>
                <p class="truncate text-[12.5px] text-white/50">
                  <template v-if="root.leader">
                    Quản lý: {{ root.leader.name }}
                  </template>
                  <template v-else>
                    {{ root.level_label }}
                  </template>
                </p>
              </div>
            </div>
            <span class="shrink-0 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-white/60">
              {{ memberCount(root) }} thành viên
            </span>
          </div>

          <div
            v-if="root.children && root.children.length"
            class="mt-5 border-t border-white/10 pt-4"
          >
            <p class="text-[11px] font-semibold uppercase tracking-wider text-white/40">
              Nhóm con
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span
                v-for="child in root.children"
                :key="child.id"
                class="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-[12.5px] text-white/75"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-brand" />
                {{ child.name }}
              </span>
            </div>
          </div>
        </article>
      </div>

      <p
        v-else
        class="mt-8 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-12 text-center text-sm text-white/45"
      >
        Sơ đồ tổ chức chưa được thiết lập.
      </p>

      <div class="mt-8">
        <Link
          href="/org-teams"
          class="inline-flex items-center gap-2 text-sm font-semibold text-brand transition hover:text-[#ff4d8d]"
        >
          Xem sơ đồ tổ chức đầy đủ
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </Link>
      </div>
    </div>
  </section>
</template>
