<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFormModal from '@/modules/contract/components/VendorFormModal.vue';
import VendorSummaryBar from '@/modules/contract/components/VendorSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';
import { watch } from 'vue';

const props = defineProps({
    vendors: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const toast = useToast();

const vendorList = computed(() => props.vendors.data ?? props.vendors ?? []);
const search = ref(props.filters.q ?? '');

let debounce;
function applySearch() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/contracts/vendors', { q: search.value || undefined }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }, 350);
}
watch(search, applySearch);

const showForm = ref(false);
const editing = ref(null);

function openCreate() {
    editing.value = null;
    showForm.value = true;
}
function openEdit(v) {
    editing.value = v;
    showForm.value = true;
}

async function onDelete(v) {
    if (v.contracts_count > 0) {
        toast.error('Không thể xoá: nhà cung cấp đang có hợp đồng.');
        return;
    }
    const ok = await dialog.confirm({
        title: 'Xoá nhà cung cấp?',
        message: `"${v.name}" sẽ bị xoá khỏi danh sách.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/vendors/${v.id}`, { preserveScroll: true });
}
</script>

<template>
  <Head title="Nhà cung cấp" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Nhà cung cấp"
        subtitle="Quản lý NCC dịch vụ, phần mềm, hạ tầng"
        icon="vendor"
        icon-color="brand"
        :badge="vendorList.length"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="15"
          /> Thêm NCC
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-6xl px-4 py-5">
      <VendorSummaryBar :summary="summary" />

      <div class="mb-4 flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
        <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
          <DatagridToolbarSearch
            v-model="search"
            input-id="vendor-search"
            input-name="q"
            hide-label
            stretch
            inline-actions
            input-height="h-10"
            placeholder="Tìm theo tên, mã, mã số thuế, người liên hệ…"
            aria-label="Tìm nhà cung cấp"
          />
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="v in vendorList"
          :key="v.id"
          class="card flex flex-col p-4"
        >
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
              <AppIcon
                name="vendor"
                :size="18"
              />
            </span>
            <div class="min-w-0 flex-1">
              <h3 class="truncate font-semibold text-slate-800">
                {{ v.name }}
              </h3>
              <p class="text-xs text-slate-400">
                {{ v.code }}
              </p>
            </div>
            <div
              v-if="v.can?.update || v.can?.delete"
              class="flex shrink-0 gap-1"
            >
              <button
                v-if="v.can?.update"
                type="button"
                class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100"
                @click="openEdit(v)"
              >
                <AppIcon
                  name="edit"
                  :size="14"
                />
              </button>
              <button
                v-if="v.can?.delete"
                type="button"
                class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                @click="onDelete(v)"
              >
                <AppIcon
                  name="delete"
                  :size="14"
                />
              </button>
            </div>
          </div>

          <dl class="mt-3 space-y-1 text-sm">
            <div
              v-if="v.contact_name"
              class="flex items-center gap-2 text-slate-500"
            >
              <AppIcon
                name="account"
                :size="13"
              /> {{ v.contact_name }}
            </div>
            <div
              v-if="v.email"
              class="flex items-center gap-2 text-slate-500"
            >
              <AppIcon
                name="mail"
                :size="13"
              /> <span class="truncate">{{ v.email }}</span>
            </div>
            <div
              v-if="v.phone"
              class="flex items-center gap-2 text-slate-500"
            >
              <AppIcon
                name="phone"
                :size="13"
              /> {{ v.phone }}
            </div>
          </dl>

          <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm">
            <span class="text-slate-400">{{ v.contracts_count ?? 0 }} hợp đồng</span>
            <span class="font-medium text-slate-700">{{ formatMoneyShort(v.total_annual_cost ?? 0) }}/năm</span>
          </div>
        </article>

        <p
          v-if="!vendorList.length"
          class="sm:col-span-2 lg:col-span-3 rounded-card border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400"
        >
          Chưa có nhà cung cấp nào.
        </p>
      </div>
    </div>

    <VendorFormModal
      :show="showForm"
      :vendor="editing"
      @close="showForm = false"
      @saved="() => router.reload({ only: ['vendors'] })"
    />
  </AppLayout>
</template>
