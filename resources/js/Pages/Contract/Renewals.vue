<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ContractCalendar from '@/modules/contract/components/ContractCalendar.vue';
import RenewalQuickModal from '@/modules/contract/components/RenewalQuickModal.vue';
import { formatMoneyShort, expiryLabel } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    calendar: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const view = ref('list'); // list | calendar

const BUCKET_TONE = {
    7: 'border-rose-200 bg-rose-50/50',
    30: 'border-amber-200 bg-amber-50/50',
    60: 'border-sky-200 bg-sky-50/50',
    90: 'border-emerald-200 bg-emerald-50/50',
};

function normalizeList(raw) {
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
}

const renewalGroups = computed(() => normalizeList(props.groups));

const total = computed(() =>
    renewalGroups.value.reduce((s, g) => s + normalizeList(g.contracts).length, 0),
);

const renewing = ref(null);
const showRenew = ref(false);
function openRenew(c) {
    renewing.value = c;
    showRenew.value = true;
}
function onSaved() {
    router.reload({ only: ['groups', 'calendar'] });
}
</script>

<template>
  <Head title="Gia hạn hợp đồng" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý gia hạn"
        subtitle="Hợp đồng sắp đến hạn — gia hạn nhanh, so sánh chi phí"
        icon="renewal"
        icon-color="brand"
        :badge="total"
      >
        <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5">
          <button
            type="button"
            class="rounded px-2.5 py-1 text-xs font-medium"
            :class="view === 'list' ? 'bg-white text-brand shadow-sm' : 'text-slate-500'"
            @click="view = 'list'"
          >
            Danh sách
          </button>
          <button
            type="button"
            class="rounded px-2.5 py-1 text-xs font-medium"
            :class="view === 'calendar' ? 'bg-white text-brand shadow-sm' : 'text-slate-500'"
            @click="view = 'calendar'"
          >
            Lịch
          </button>
        </div>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-7xl px-4 py-5">
      <div v-if="view === 'calendar'">
        <ContractCalendar :contracts="normalizeList(calendar)" />
      </div>

      <div
        v-else
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
      >
        <section
          v-for="g in renewalGroups"
          :key="g.days"
          class="rounded-card border p-3"
          :class="BUCKET_TONE[g.days] ?? 'border-slate-200'"
        >
          <header class="mb-3 flex items-center justify-between">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              {{ g.label }}
            </h2>
            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600">{{ normalizeList(g.contracts).length }}</span>
          </header>

          <ul class="space-y-2">
            <li
              v-for="c in normalizeList(g.contracts)"
              :key="c.id"
              class="rounded-lg border border-slate-200 bg-white p-2.5"
            >
              <div class="flex items-start justify-between gap-2">
                <Link
                  :href="`/contracts/${c.id}`"
                  class="min-w-0 flex-1"
                >
                  <p class="truncate text-sm font-medium text-slate-800 hover:text-brand">
                    {{ c.name }}
                  </p>
                  <p class="text-xs text-slate-400">
                    {{ c.code }}
                  </p>
                </Link>
                <Badge
                  :label="c.status.label"
                  :color="c.status.color"
                />
              </div>
              <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-500">{{ expiryLabel(c.days_until_expiry) }}</span>
                <span class="font-medium text-slate-600">{{ formatMoneyShort(c.annual_cost) }}/năm</span>
              </div>
              <button
                v-if="can.manage"
                type="button"
                class="btn-primary mt-2 w-full text-xs"
                @click="openRenew(c)"
              >
                <AppIcon
                  name="renewal"
                  :size="13"
                /> Gia hạn
              </button>
            </li>
            <li
              v-if="!normalizeList(g.contracts).length"
              class="py-4 text-center text-xs text-slate-400"
            >
              Không có hợp đồng.
            </li>
          </ul>
        </section>
      </div>
    </div>

    <RenewalQuickModal
      :show="showRenew"
      :contract="renewing"
      @close="showRenew = false"
      @saved="onSaved"
    />
  </AppLayout>
</template>
