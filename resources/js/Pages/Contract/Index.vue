<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ContractExplorer from '@/modules/contract/components/ContractExplorer.vue';
import ContractFormModal from '@/modules/contract/components/ContractFormModal.vue';
import ContractDataModal from '@/modules/contract/components/ContractDataModal.vue';
import { useContractExplorer } from '@/modules/contract/composables/useContractExplorer.js';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    contracts: { type: Object, required: true }, // resource collection { data: [...] }
    vendors: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const contractList = computed(() => {
    const raw = props.contracts?.data ?? props.contracts;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
});
const { tree, totals } = useContractExplorer(contractList, () => props.vendors, () => props.categories);

const search = ref(props.filters.q ?? '');
const statusFilter = ref(props.filters.status ?? '');

function applyFilters() {
    router.get('/contracts', {
        q: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

const showForm = ref(false);
const showData = ref(false);

function onSaved() {
    router.reload({ only: ['contracts'] });
}
</script>

<template>
  <Head title="Danh mục hợp đồng" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Danh mục hợp đồng"
        subtitle="Khám phá theo nhà cung cấp → nhóm dịch vụ → hợp đồng"
        icon="documents"
        icon-color="brand"
        :badge="totals.contracts"
      >
        <button
          type="button"
          class="btn-ghost"
          @click="showData = true"
        >
          <AppIcon
            name="columns"
            :size="15"
          /> Dữ liệu
        </button>
        <button
          v-if="can.create"
          type="button"
          class="btn-primary"
          @click="showForm = true"
        >
          <AppIcon
            name="add"
            :size="15"
          /> Tạo hợp đồng
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-6xl px-4 py-5">
      <!-- Toolbar -->
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[200px]">
          <AppIcon
            name="search"
            :size="15"
            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="search"
            class="input pl-9"
            placeholder="Tìm theo tên, mã, đơn vị…"
            @keyup.enter="applyFilters"
          >
        </div>
        <select
          v-model="statusFilter"
          class="input w-44"
          @change="applyFilters"
        >
          <option value="">
            Tất cả trạng thái
          </option>
          <option
            v-for="o in options.status"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
        <button
          type="button"
          class="btn-ghost"
          @click="applyFilters"
        >
          <AppIcon
            name="filter"
            :size="15"
          /> Lọc
        </button>
        <span class="ml-auto text-sm text-slate-500">
          Tổng chi phí năm: <strong class="text-slate-700">{{ formatMoneyShort(totals.annualCost) }}</strong>
        </span>
      </div>

      <ContractExplorer :tree="tree" />
    </div>

    <ContractFormModal
      :show="showForm"
      :vendors="vendors"
      :categories="categories"
      :employees="options.employees || []"
      :departments="options.departments || []"
      :status-options="options.status || []"
      :payment-options="options.paymentStatus || []"
      @close="showForm = false"
      @saved="onSaved"
    />

    <ContractDataModal
      :show="showData"
      :can-manage="can.import"
      :vendors="vendors"
      :categories="categories"
      :employees="options.employees || []"
      :status-options="options.status || []"
      :payment-options="options.paymentStatus || []"
      @close="showData = false"
      @imported="onSaved"
    />
  </AppLayout>
</template>
