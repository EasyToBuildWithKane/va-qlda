<script setup>
import {
    computed, ref, watch, onMounted, onBeforeUnmount,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AuditTrailSummaryBar from '@/modules/audit/components/AuditTrailSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { buildClientPaginationLinks } from '@/shared/composables/useClientPagination';
import { useFilter } from '@/shared/composables/useFilter';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import {
    displayOrEmpty,
    EMPTY_LABELS,
    isEmptyDisplayValue,
} from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    trend: { type: Array, default: () => [] },
    byModule: { type: Array, default: () => [] },
    options: { type: Object, required: true },
});

const SEVERITY = {
    info: { dot: 'bg-slate-400', badge: 'bg-slate-100 text-slate-600', label: 'Thông tin' },
    notice: { dot: 'bg-sky-500', badge: 'bg-sky-100 text-sky-700', label: 'Ghi nhận' },
    warning: { dot: 'bg-amber-500', badge: 'bg-amber-100 text-amber-700', label: 'Cảnh báo' },
    critical: { dot: 'bg-rose-500', badge: 'bg-rose-100 text-rose-700', label: 'Nghiêm trọng' },
};
const sev = (s) => SEVERITY[s] ?? SEVERITY.info;

const FILTER_CONTROLS = [
    { key: 'module', label: 'Module', default: false },
    { key: 'actor_account_id', label: 'Người dùng', default: false },
    { key: 'date_range', label: 'Thời gian', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const filterPanelDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || 25);

const { filters: form } = useFilter({
    module: props.filters.module ?? null,
    action: props.filters.action ?? null,
    actor_account_id: props.filters.actor_account_id ?? null,
    search: props.filters.search ?? null,
    from: props.filters.from ?? null,
    to: props.filters.to ?? null,
    per_page: props.filters.per_page ?? 25,
    page: props.meta.current_page ?? 1,
});

watch(
    () => [form.module, form.action, form.actor_account_id, form.search, form.from, form.to],
    () => {
        form.page = 1;
    },
);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: filterControlDefs,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-workspace.audit-trail.visible-filters.v1');

function openFilterPanelSafe() {
    openFilterPanel();
}

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function isoDate(d) {
    return d.toISOString().slice(0, 10);
}

const todayIso = isoDate(new Date());

function weekAgoIso() {
    const d = new Date();
    d.setDate(d.getDate() - 6);
    return isoDate(d);
}

const activeKpi = computed(() => {
    if (form.action === 'auth.login_failed') return 'login_failed';
    if (form.from === todayIso && form.to === todayIso) return 'today';
    if (form.from === weekAgoIso() && form.to === todayIso && !form.module && !form.action) return 'week';
    if (!form.module && !form.action && !form.actor_account_id && !form.search && !form.from && !form.to) {
        return 'total';
    }
    return '';
});

function onQuickFilter({ kpi }) {
    form.module = null;
    form.action = null;
    form.actor_account_id = null;
    form.search = null;
    form.from = null;
    form.to = null;

    if (kpi === 'today') {
        form.from = todayIso;
        form.to = todayIso;
    } else if (kpi === 'week') {
        form.from = weekAgoIso();
        form.to = todayIso;
    } else if (kpi === 'login_failed') {
        form.action = 'auth.login_failed';
        form.from = weekAgoIso();
        form.to = todayIso;
    }

    form.page = 1;
}

function onPerPageChange(n) {
    perPage.value = n;
    form.per_page = n;
    form.page = 1;
}

function goToPage(p) {
    if (p < 1 || p > props.meta.last_page || p === props.meta.current_page) return;
    form.page = p;
}

const paginationMeta = computed(() => ({
    ...props.meta,
    links: buildClientPaginationLinks(props.meta.current_page, props.meta.last_page),
}));

/** Laravel JsonResource collection có thể bọc { data: [...] } — chuẩn hoá thành mảng phẳng. */
const logsList = computed(() => {
    const raw = props.logs;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object' && Array.isArray(raw.data)) return raw.data;
    return [];
});

const maxTrend = computed(() => Math.max(1, ...props.trend.map((t) => t.count)));

const AUDIT_LOG_ICONS = new Set([
    'account',
    'system-config',
    'members',
    'sparkles',
    'documents',
    'knowledge',
    'learning',
    'org-teams',
    'department',
    'rocket',
    'settings',
    'shield',
]);

const expanded = ref({});
const toggle = (id) => { expanded.value[id] = !expanded.value[id]; };
const hasMeta = (log) => log.meta && Object.keys(log.meta).length > 0;
const formatMeta = (meta) =>
    Object.entries(meta).map(([k, v]) => ({
        key: k,
        value: typeof v === 'object' ? JSON.stringify(v) : String(v),
    }));

function resolveLogIcon(log) {
    const name = log?.icon;
    return name && AUDIT_LOG_ICONS.has(name) ? name : 'shield';
}

function moduleLabel(log) {
    return displayOrEmpty(log?.module_label, 'Chưa xác định module');
}

function subjectSummary(log) {
    if (!isEmptyDisplayValue(log?.subject_summary)) {
        return log.subject_summary;
    }
    const type = log?.subject_type?.trim();
    const id = log?.subject_id;
    if (!type && (id === null || id === undefined || id === '')) {
        return EMPTY_LABELS.generic;
    }
    const idPart = id !== null && id !== undefined && id !== '' ? `#${id}` : '';
    return `${type || 'Đối tượng'}${idPart}`.trim();
}

function detailPreview(log) {
    if (!isEmptyDisplayValue(log?.detail_preview)) {
        return log.detail_preview;
    }
    if (!hasMeta(log)) {
        return EMPTY_LABELS.generic;
    }
    const rows = formatMeta(log.meta);
    if (rows.length === 1) {
        return `${rows[0].key}: ${rows[0].value}`;
    }
    return `${rows.length} trường — bấm «Chi tiết» để xem đầy đủ`;
}

function filterByModule(moduleKey) {
    form.module = moduleKey;
    form.action = null;
    form.page = 1;
}
</script>

<template>
  <Head title="Nhật ký truy vết" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Nhật ký truy vết"
        subtitle="Sổ cái hợp nhất mọi thao tác cấu hình, phân quyền, đăng nhập & vòng đời dữ liệu"
        icon="shield"
      />
    </template>

    <AuditTrailSummaryBar
      :stats="stats"
      :active-kpi="activeKpi"
      @quick-filter="onQuickFilter"
    />

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
      <div class="min-w-0 xl:col-span-8">
        <div class="card overflow-visible">
          <div class="relative z-20 border-b border-slate-100 px-5 py-4">
            <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
              <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
                <DatagridToolbarSearch
                  v-model="form.search"
                  input-id="audit-trail-search"
                  hide-label
                  stretch
                  inline-actions
                  input-height="h-10"
                  placeholder="Tìm theo hành động, đối tượng, dữ liệu…"
                  aria-label="Tìm nhật ký truy vết"
                />
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <div
                  ref="filterPanelDdRef"
                  class="relative shrink-0"
                >
                  <DatagridToolbarActionButton
                    icon="filter"
                    :active="showFilterPanelDd"
                    :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${filterControlDefs.length})`"
                    @click="openFilterPanelSafe"
                  >
                    Lọc
                  </DatagridToolbarActionButton>
                  <FilterVisibilityDropdown
                    v-model="visibleFilters"
                    :show="showFilterPanelDd"
                    :anchor-ref="filterPanelDdRef"
                    :controls="filterControlDefs"
                    input-id-prefix="audit-trail-filter-vis"
                    @persist="persistVisibleFilters"
                  />
                </div>
              </div>
            </div>

            <Transition name="fade-slide">
              <div
                v-if="hasFilterRow"
                class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
              >
                <DatagridFilterField v-if="visibleFilters.module">
                  <select
                    v-model="form.module"
                    :class="FILTER_CONTROL_CLASS"
                    aria-label="Module"
                  >
                    <option :value="null">
                      Module
                    </option>
                    <option
                      v-for="m in options.modules"
                      :key="m.key"
                      :value="m.key"
                    >
                      {{ m.label }}
                    </option>
                  </select>
                </DatagridFilterField>

                <DatagridFilterField v-if="visibleFilters.actor_account_id">
                  <select
                    v-model="form.actor_account_id"
                    :class="FILTER_CONTROL_CLASS"
                    aria-label="Người dùng"
                  >
                    <option :value="null">
                      Người dùng
                    </option>
                    <option
                      v-for="a in options.actors"
                      :key="a.id"
                      :value="a.id"
                    >
                      {{ a.display_name }}
                    </option>
                  </select>
                </DatagridFilterField>

                <DatagridFilterField
                  v-if="visibleFilters.date_range"
                  class="sm:col-span-2 xl:col-span-2"
                >
                  <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <FilterDatePicker
                      v-model="form.from"
                      placeholder="Từ ngày"
                    />
                    <FilterDatePicker
                      v-model="form.to"
                      placeholder="Đến ngày"
                    />
                  </div>
                </DatagridFilterField>
              </div>
            </Transition>
          </div>

          <div class="divide-y divide-slate-100">
            <div
              v-if="logsList.length === 0"
              class="py-16 text-center text-[13px] text-slate-400"
            >
              <AppIcon
                name="shield"
                :size="28"
                class="mx-auto mb-2 opacity-40"
              />
              Không có bản ghi phù hợp.
            </div>
            <div
              v-for="log in logsList"
              :key="log.id"
              class="px-5 py-3 transition-colors hover:bg-slate-50/60"
            >
              <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                  <AppIcon
                    :name="resolveLogIcon(log)"
                    :size="15"
                  />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[13px] font-medium text-slate-800">
                      {{ displayOrEmpty(log.action_label, log.action) }}
                    </span>
                    <span
                      class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                      :class="sev(log.severity).badge"
                    >
                      {{ sev(log.severity).label }}
                    </span>
                    <span
                      v-if="!isEmptyDisplayValue(log.module_label)"
                      class="inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
                    >
                      {{ log.module_label }}
                    </span>
                    <span
                      v-else
                      class="text-[11px] text-slate-400"
                    >
                      {{ moduleLabel(log) }}
                    </span>
                  </div>
                  <p class="mt-0.5 text-[12px] text-slate-500">
                    <span class="font-medium text-slate-600">{{ log.actor?.name ?? 'Hệ thống' }}</span>
                    <span> · {{ subjectSummary(log) }}</span>
                    <span class="text-slate-400"> · {{ log.created_at_human }}</span>
                  </p>
                  <p class="mt-1 text-[11px] text-slate-500">
                    {{ detailPreview(log) }}
                  </p>
                  <button
                    v-if="hasMeta(log)"
                    type="button"
                    class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-400 hover:text-brand"
                    @click="toggle(log.id)"
                  >
                    <AppIcon
                      :name="expanded[log.id] ? 'chevron-down' : 'chevron-right'"
                      :size="12"
                    />
                    Chi tiết
                  </button>
                  <div
                    v-if="expanded[log.id] && hasMeta(log)"
                    class="mt-2 space-y-1 rounded-lg border border-slate-100 bg-slate-50 p-2"
                  >
                    <div
                      v-for="row in formatMeta(log.meta)"
                      :key="row.key"
                      class="flex gap-2 text-[11px]"
                    >
                      <span class="min-w-[90px] shrink-0 text-slate-400">{{ row.key }}</span>
                      <span class="break-all text-slate-600">{{ row.value }}</span>
                    </div>
                  </div>
                </div>
                <span
                  class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                  :class="sev(log.severity).dot"
                />
              </div>
            </div>
          </div>

          <DatagridPaginationFooter
            v-if="meta.total > 0"
            variant="bar"
            client
            :meta="paginationMeta"
            :per-page="perPage"
            :per-page-options="options.perPage"
            @update:per-page="onPerPageChange"
            @page-change="goToPage"
          />
        </div>
      </div>

      <aside class="min-w-0 space-y-5 xl:col-span-4">
        <div class="card p-4">
          <h3 class="mb-3 text-[13px] font-semibold text-slate-700">
            Xu hướng 14 ngày
          </h3>
          <div
            v-if="trend.length"
            class="flex h-20 items-end gap-1"
          >
            <div
              v-for="t in trend"
              :key="t.date"
              class="min-h-[2px] flex-1 rounded-t bg-brand/70"
              :style="{ height: `${Math.round((t.count / maxTrend) * 100)}%` }"
              :title="`${t.date}: ${t.count}`"
            />
          </div>
          <p
            v-else
            class="text-[12px] text-slate-400"
          >
            Chưa có dữ liệu.
          </p>
        </div>

        <div class="card p-4">
          <h3 class="mb-3 text-[13px] font-semibold text-slate-700">
            Theo module (30 ngày)
          </h3>
          <div class="space-y-2">
            <button
              v-for="m in byModule"
              :key="m.module"
              type="button"
              class="group flex w-full items-center gap-2.5 text-left"
              @click="filterByModule(m.module)"
            >
              <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 group-hover:bg-brand/10 group-hover:text-brand">
                <AppIcon
                  :name="resolveLogIcon(m)"
                  :size="14"
                />
              </div>
              <span class="flex-1 truncate text-[12px] text-slate-600">{{ displayOrEmpty(m.module_label, 'Chưa xác định module') }}</span>
              <span class="text-[12px] font-semibold text-slate-700">{{ m.count }}</span>
            </button>
            <p
              v-if="byModule.length === 0"
              class="text-[12px] text-slate-400"
            >
              Chưa có dữ liệu.
            </p>
          </div>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
