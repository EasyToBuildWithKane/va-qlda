<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AlertSummaryBar from '@/modules/contract/components/AlertSummaryBar.vue';
import { expiryLabel } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    alerts: { type: Object, required: true },
});

const items = computed(() => props.alerts.items || []);
const summary = computed(() => props.alerts.summary || {});

const TYPE_META = {
    expiring: { label: 'Sắp hết hạn', icon: 'clock' },
    expired: { label: 'Đã hết hạn', icon: 'alert' },
    unpaid: { label: 'Chưa thanh toán', icon: 'budget' },
    missing_docs: { label: 'Thiếu hồ sơ', icon: 'documents' },
};
const LEVEL_META = {
    critical: { label: 'Nghiêm trọng', chip: 'bg-rose-100 text-rose-700', dot: 'bg-rose-500' },
    high: { label: 'Cao', chip: 'bg-amber-100 text-amber-700', dot: 'bg-amber-500' },
    medium: { label: 'Trung bình', chip: 'bg-sky-100 text-sky-700', dot: 'bg-sky-500' },
    low: { label: 'Thấp', chip: 'bg-slate-100 text-slate-600', dot: 'bg-slate-400' },
};

const levelFilter = ref('all');
const typeFilter = ref('all');

const filtered = computed(() => items.value.filter((i) =>
    (levelFilter.value === 'all' || i.level === levelFilter.value)
    && (typeFilter.value === 'all' || i.type === typeFilter.value)));

const typeChips = computed(() => [
    { key: 'all', label: 'Tất cả loại' },
    { key: 'expiring', label: `Sắp hết hạn (${summary.value.expiring ?? 0})` },
    { key: 'expired', label: `Đã hết hạn (${summary.value.expired ?? 0})` },
    { key: 'unpaid', label: `Chưa thanh toán (${summary.value.unpaid ?? 0})` },
    { key: 'missing_docs', label: `Thiếu hồ sơ (${summary.value.missing_docs ?? 0})` },
]);
</script>

<template>
  <Head title="Trung tâm cảnh báo" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Trung tâm cảnh báo"
        subtitle="Hợp đồng cần chú ý — sắp hết hạn, quá hạn, chưa thanh toán, thiếu hồ sơ"
        icon="alert"
        icon-color="brand"
        :badge="summary.total"
      />
    </template>

    <div class="mx-auto max-w-7xl px-4 py-5">
      <!-- Level summary strip (clickable filter) -->
      <AlertSummaryBar
        :summary="summary"
        :active-level="levelFilter"
        @select-level="levelFilter = $event"
      />

      <!-- Type filter chips -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="t in typeChips"
          :key="t.key"
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-medium transition"
          :class="typeFilter === t.key ? 'border-brand bg-brand/10 text-brand' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
          @click="typeFilter = t.key"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- Alert list -->
      <ul class="divide-y divide-slate-100 rounded-card border border-slate-200 bg-white">
        <li
          v-for="(a, idx) in filtered"
          :key="`${a.type}-${a.id}-${idx}`"
          class="flex items-center gap-3 px-4 py-3"
        >
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
            :class="LEVEL_META[a.level]?.chip"
          >
            <AppIcon
              :name="TYPE_META[a.type]?.icon ?? 'alert'"
              :size="16"
            />
          </span>
          <div class="min-w-0 flex-1">
            <Link
              :href="`/contracts/${a.id}`"
              class="block truncate text-sm font-medium text-slate-800 hover:text-brand"
            >
              {{ a.name }}
            </Link>
            <p class="text-xs text-slate-400">
              {{ a.code }}<span v-if="a.vendor"> · {{ a.vendor }}</span>
            </p>
          </div>
          <span
            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
            :class="LEVEL_META[a.level]?.chip"
          >
            {{ TYPE_META[a.type]?.label }}
          </span>
          <span
            v-if="a.type !== 'unpaid' && a.type !== 'missing_docs'"
            class="hidden w-28 shrink-0 text-right text-xs text-slate-400 sm:block"
          >
            {{ expiryLabel(a.days_until_expiry) }}
          </span>
        </li>
        <li
          v-if="!filtered.length"
          class="px-4 py-12 text-center text-sm text-slate-400"
        >
          Không có cảnh báo phù hợp. 🎉
        </li>
      </ul>
    </div>
  </AppLayout>
</template>
