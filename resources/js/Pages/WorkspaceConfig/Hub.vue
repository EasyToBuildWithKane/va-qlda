<script setup>
import { computed, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import WorkspaceConfigSummaryBar from '@/modules/workspace-config/components/WorkspaceConfigSummaryBar.vue';
import WorkspaceProfileGrid from '@/modules/workspace-config/components/WorkspaceProfileGrid.vue';

const props = defineProps({
    workspaces: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0 }) },
    viewer: {
        type: Object,
        default: () => ({
            can_manage: false,
            own_department_code: null,
            is_super_admin: false,
        }),
    },
    statusOptions: { type: Array, default: () => [] },
});

const search = ref('');
const filters = reactive({
    status: '',
});

const statusSegments = computed(() => {
    const base = [
        { key: '', label: 'Tất cả' },
        { key: 'active', label: 'Đang dùng' },
        { key: 'draft', label: 'Nháp' },
    ];
    if (props.viewer.can_manage) {
        base.push({ key: 'missing', label: 'Chưa cấu hình' });
    }
    return base;
});

const filteredWorkspaces = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.workspaces.filter((ws) => {
        if (filters.status && ws.status !== filters.status) {
            return false;
        }
        if (!q) return true;
        return ws.department_name.toLowerCase().includes(q)
            || ws.department_code.toLowerCase().includes(q);
    });
});

function onQuickFilter(payload) {
    filters.status = payload?.status ?? '';
}

const emptyHint = computed(() => {
    if (!props.viewer.can_manage && !props.viewer.own_department_code) {
        return 'Tài khoản chưa gắn phòng ban HRM — không thể mở workspace phòng ban.';
    }
    if (props.workspaces.length === 0) {
        return 'Chưa có phòng ban nào trong danh mục hoặc bạn không được phép xem.';
    }
    return 'Không có workspace khớp bộ lọc hiện tại.';
});
</script>

<template>
  <Head title="Cấu hình workspace" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình workspace"
        subtitle="Workspace theo phòng ban — siêu quản trị cấu hình toàn bộ; mỗi người chỉ thấy đúng phòng ban HRM của mình"
        icon="system-config"
        icon-color="brand"
        :badge="summary.total || null"
      />
    </template>

    <WorkspaceConfigSummaryBar
      class="mb-5"
      :summary="summary"
      :active-status="filters.status"
      :can-manage="viewer.can_manage"
      @quick-filter="onQuickFilter"
    />

    <section
      class="kpi-strip relative mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
      aria-label="Danh sách workspace phòng ban"
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
          Workspace
        </p>
        <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
          Phòng ban
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Chọn phòng ban để mở các mục cấu hình nghiệp vụ (đánh giá và các module mở rộng).
        </p>
      </header>

      <div
        v-if="viewer.can_manage || workspaces.length > 1"
        class="relative flex w-full min-w-0 flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3 lg:flex-nowrap"
      >
        <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
          <DatagridToolbarSearch
            v-model="search"
            hide-label
            stretch
            inline-actions
            input-height="h-10"
            placeholder="Tìm phòng ban theo tên hoặc mã…"
            aria-label="Tìm phòng ban"
          />
        </div>
        <div class="ml-auto flex shrink-0 items-center gap-2">
          <DatagridSegmentedControl
            v-model="filters.status"
            :items="statusSegments"
            aria-label="Lọc trạng thái workspace"
            icon-only-below-sm
          />
        </div>
      </div>

      <div class="relative p-4 md:p-5">
        <WorkspaceProfileGrid
          v-if="filteredWorkspaces.length"
          :workspaces="filteredWorkspaces"
          :can-manage="viewer.can_manage"
        />
        <EmptyState
          v-else
          icon="department"
          title="Chưa có workspace để hiển thị"
          :description="emptyHint"
        />
      </div>
    </section>
  </AppLayout>
</template>
