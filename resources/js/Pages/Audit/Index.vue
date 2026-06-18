<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useFilter } from '@/shared/composables/useFilter';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    trend: { type: Array, default: () => [] },
    byModule: { type: Array, default: () => [] },
    options: { type: Object, required: true },
});

// ─── Severity styling ───────────────────────────────────────────────────────
const SEVERITY = {
    info: { dot: 'bg-slate-400', badge: 'bg-slate-100 text-slate-600', label: 'Thông tin' },
    notice: { dot: 'bg-sky-500', badge: 'bg-sky-100 text-sky-700', label: 'Ghi nhận' },
    warning: { dot: 'bg-amber-500', badge: 'bg-amber-100 text-amber-700', label: 'Cảnh báo' },
    critical: { dot: 'bg-rose-500', badge: 'bg-rose-100 text-rose-700', label: 'Nghiêm trọng' },
};
const sev = (s) => SEVERITY[s] ?? SEVERITY.info;

// ─── Stat cards ─────────────────────────────────────────────────────────────
const statCards = computed(() => [
    { key: 'total', label: 'Tổng sự kiện', value: props.stats.total, icon: 'shield', color: 'text-brand bg-brand/10' },
    { key: 'today', label: 'Hôm nay', value: props.stats.today, icon: 'calendar', color: 'text-sky-600 bg-sky-100' },
    { key: 'week', label: '7 ngày qua', value: props.stats.week, icon: 'overview', color: 'text-violet-600 bg-violet-100' },
    { key: 'login_failed', label: 'Đăng nhập lỗi (7 ngày)', value: props.stats.login_failed, icon: 'flag', color: 'text-rose-600 bg-rose-100' },
]);

const maxTrend = computed(() => Math.max(1, ...props.trend.map((t) => t.count)));

// ─── Filters (URL-bound via Inertia, debounced) ─────────────────────────────
const { filters: form } = useFilter({
    module: props.filters.module ?? null,
    actor_account_id: props.filters.actor_account_id ?? null,
    search: props.filters.search ?? null,
    from: props.filters.from ?? null,
    to: props.filters.to ?? null,
    per_page: props.filters.per_page ?? 25,
});

function resetFilters() {
    form.module = null;
    form.actor_account_id = null;
    form.search = null;
    form.from = null;
    form.to = null;
}

const hasFilters = computed(() =>
    !!(form.module || form.actor_account_id || form.search || form.from || form.to),
);

function goToPage(p) {
    if (p < 1 || p > props.meta.last_page || p === props.meta.current_page) return;
    const params = Object.fromEntries(
        Object.entries({ ...form }).filter(([, v]) => v !== null && v !== '' && v !== undefined),
    );
    router.get(route('audit.index'), { ...params, page: p }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

// ─── Row meta expansion ─────────────────────────────────────────────────────
const expanded = ref({});
const toggle = (id) => (expanded.value[id] = !expanded.value[id]);
const hasMeta = (log) => log.meta && Object.keys(log.meta).length > 0;
const formatMeta = (meta) =>
    Object.entries(meta).map(([k, v]) => ({
        key: k,
        value: typeof v === 'object' ? JSON.stringify(v) : String(v),
    }));
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

    <div class="mx-auto max-w-7xl px-4 py-5 space-y-5">
      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div
          v-for="c in statCards"
          :key="c.key"
          class="rounded-xl border border-slate-200/70 bg-white p-4 flex items-center gap-3"
        >
          <div
            class="h-10 w-10 rounded-lg flex items-center justify-center shrink-0"
            :class="c.color"
          >
            <AppIcon
              :name="c.icon"
              :size="18"
            />
          </div>
          <div class="min-w-0">
            <p class="text-xl font-semibold text-slate-800 leading-none">
              {{ c.value }}
            </p>
            <p class="text-[12px] text-slate-400 mt-1 truncate">
              {{ c.label }}
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Main feed -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Filter toolbar -->
          <div class="rounded-xl border border-slate-200/70 bg-white p-3 space-y-3">
            <div class="flex items-center gap-2">
              <div class="relative flex-1">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                  <AppIcon
                    name="search"
                    :size="15"
                  />
                </span>
                <input
                  v-model="form.search"
                  type="text"
                  placeholder="Tìm theo hành động, đối tượng, dữ liệu…"
                  class="w-full rounded-lg border border-slate-200 pl-9 pr-3 py-2 text-[13px] focus:border-brand focus:ring-1 focus:ring-brand"
                >
              </div>
              <button
                v-if="hasFilters"
                type="button"
                class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-2 text-[12px] text-slate-500 hover:bg-slate-50"
                @click="resetFilters"
              >
                <AppIcon
                  name="close"
                  :size="14"
                />
                Xóa lọc
              </button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <select
                v-model="form.module"
                class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
              >
                <option value="">
                  Tất cả module
                </option>
                <option
                  v-for="m in options.modules"
                  :key="m.key"
                  :value="m.key"
                >
                  {{ m.label }}
                </option>
              </select>
              <select
                v-model="form.actor_account_id"
                class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
              >
                <option value="">
                  Mọi người dùng
                </option>
                <option
                  v-for="a in options.actors"
                  :key="a.id"
                  :value="a.id"
                >
                  {{ a.display_name }}
                </option>
              </select>
              <input
                v-model="form.from"
                type="date"
                class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
              >
              <input
                v-model="form.to"
                type="date"
                class="rounded-lg border border-slate-200 px-2 py-1.5 text-[12px] focus:border-brand focus:ring-1 focus:ring-brand"
              >
            </div>
          </div>

          <!-- Feed list -->
          <div class="rounded-xl border border-slate-200/70 bg-white divide-y divide-slate-100">
            <div
              v-if="logs.length === 0"
              class="py-16 text-center text-slate-400 text-[13px]"
            >
              <AppIcon
                name="shield"
                :size="28"
                class="mx-auto mb-2 opacity-40"
              />
              Không có bản ghi phù hợp.
            </div>
            <div
              v-for="log in logs"
              :key="log.id"
              class="px-4 py-3 hover:bg-slate-50/60 transition-colors"
            >
              <div class="flex items-start gap-3">
                <div class="mt-0.5 h-8 w-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                  <AppIcon
                    :name="log.icon"
                    :size="15"
                  />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[13px] font-medium text-slate-800">
                      {{ log.action_label }}
                    </span>
                    <span
                      class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                      :class="sev(log.severity).badge"
                    >
                      {{ sev(log.severity).label }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                      {{ log.module_label }}
                    </span>
                  </div>
                  <p class="text-[12px] text-slate-500 mt-0.5">
                    <span class="font-medium text-slate-600">{{ log.actor?.name ?? 'Hệ thống' }}</span>
                    <span v-if="log.subject_type"> · {{ log.subject_type }}<span v-if="log.subject_id">#{{ log.subject_id }}</span></span>
                    <span class="text-slate-400"> · {{ log.created_at_human }}</span>
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
                    class="mt-2 rounded-lg bg-slate-50 border border-slate-100 p-2 space-y-1"
                  >
                    <div
                      v-for="row in formatMeta(log.meta)"
                      :key="row.key"
                      class="flex gap-2 text-[11px]"
                    >
                      <span class="text-slate-400 shrink-0 min-w-[90px]">{{ row.key }}</span>
                      <span class="text-slate-600 break-all">{{ row.value }}</span>
                    </div>
                  </div>
                </div>
                <span
                  class="mt-1.5 h-2 w-2 rounded-full shrink-0"
                  :class="sev(log.severity).dot"
                />
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div
            v-if="meta.last_page > 1"
            class="flex items-center justify-between text-[12px] text-slate-500"
          >
            <span>{{ meta.from }}–{{ meta.to }} trong {{ meta.total }}</span>
            <div class="flex items-center gap-1">
              <button
                class="rounded-lg border border-slate-200 px-2.5 py-1.5 disabled:opacity-40 hover:bg-slate-50"
                :disabled="meta.current_page <= 1"
                @click="goToPage(meta.current_page - 1)"
              >
                Trước
              </button>
              <span class="px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
              <button
                class="rounded-lg border border-slate-200 px-2.5 py-1.5 disabled:opacity-40 hover:bg-slate-50"
                :disabled="meta.current_page >= meta.last_page"
                @click="goToPage(meta.current_page + 1)"
              >
                Sau
              </button>
            </div>
          </div>
        </div>

        <!-- Sidebar: trend + module breakdown -->
        <div class="space-y-5">
          <div class="rounded-xl border border-slate-200/70 bg-white p-4">
            <h3 class="text-[13px] font-semibold text-slate-700 mb-3">
              Xu hướng 14 ngày
            </h3>
            <div class="flex items-end gap-1 h-20">
              <div
                v-for="t in trend"
                :key="t.date"
                class="flex-1 rounded-t bg-brand/70 min-h-[2px]"
                :style="{ height: `${Math.round((t.count / maxTrend) * 100)}%` }"
                :title="`${t.date}: ${t.count}`"
              />
              <div
                v-if="trend.length === 0"
                class="text-[12px] text-slate-400"
              >
                Chưa có dữ liệu.
              </div>
            </div>
          </div>

          <div class="rounded-xl border border-slate-200/70 bg-white p-4">
            <h3 class="text-[13px] font-semibold text-slate-700 mb-3">
              Theo module (30 ngày)
            </h3>
            <div class="space-y-2">
              <button
                v-for="m in byModule"
                :key="m.module"
                type="button"
                class="w-full flex items-center gap-2.5 text-left group"
                @click="form.module = m.module"
              >
                <div class="h-7 w-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0 group-hover:bg-brand/10 group-hover:text-brand">
                  <AppIcon
                    :name="m.icon"
                    :size="14"
                  />
                </div>
                <span class="text-[12px] text-slate-600 flex-1 truncate">{{ m.module_label }}</span>
                <span class="text-[12px] font-semibold text-slate-700">{{ m.count }}</span>
              </button>
              <div
                v-if="byModule.length === 0"
                class="text-[12px] text-slate-400"
              >
                Chưa có dữ liệu.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
