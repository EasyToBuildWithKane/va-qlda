<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import WorkspaceConfigItemGrid from '@/modules/workspace-config/components/WorkspaceConfigItemGrid.vue';

const props = defineProps({
    workspace: { type: Object, required: true },
    modules: { type: Array, default: () => [] },
    viewer: {
        type: Object,
        default: () => ({ can_manage: false, own_department_code: null }),
    },
});

const statusClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    draft: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    missing: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    archived: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};

const subtitle = computed(() => {
    const code = props.workspace.department_code;
    return `Mã ${code} · Tiêu chí phòng ban: ${props.workspace.criteria_count} · Tiêu chí chung: ${props.workspace.criteria_general}`;
});

function ensureWorkspace() {
    router.post(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}/ensure`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
  <Head :title="`Workspace · ${workspace.department_name}`" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="workspace.department_name"
        :subtitle="subtitle"
        icon="department"
        icon-color="brand"
        back-href="/workspace-config"
      >
        <span
          class="inline-flex items-center rounded-md px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ring-1"
          :class="statusClass[workspace.status] ?? statusClass.missing"
        >
          {{ workspace.status_label }}
        </span>
        <button
          v-if="viewer.can_manage && workspace.status === 'missing'"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="ensureWorkspace"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Kích hoạt workspace
        </button>
        <Link
          :href="`/workspace-config/evaluation?department_code=${encodeURIComponent(workspace.department_code)}&scope=department`"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="award"
            :size="15"
          />
          Tiêu chí đánh giá
        </Link>
      </PageHeader>
    </template>

    <section
      class="kpi-strip relative mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
      aria-label="Mục cấu hình workspace phòng ban"
    >
      <div
        class="kpi-strip__bg-outer pointer-events-none absolute -right-6 top-0 h-full w-1/2 bg-gradient-to-l from-brand/[0.04] to-transparent"
        aria-hidden="true"
      />
      <div
        class="kpi-strip__bg-inner pointer-events-none absolute right-0 top-0 h-24 w-32 bg-gradient-to-bl from-brand/[0.06] to-transparent"
        aria-hidden="true"
      />

      <header class="relative border-b border-slate-100/80 px-5 py-4">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Module
        </p>
        <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
          Cấu hình nghiệp vụ
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Các mục gắn với workspace phòng ban này. Mục «Sắp ra mắt» sẽ xuất hiện khi được triển khai.
        </p>
      </header>

      <div class="relative p-4 md:p-5">
        <WorkspaceConfigItemGrid :items="modules" />
        <p
          v-if="!modules.length"
          class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200/70"
        >
          Chưa có mục cấu hình nào bạn được phép xem trong workspace này.
        </p>
      </div>
    </section>
  </AppLayout>
</template>
