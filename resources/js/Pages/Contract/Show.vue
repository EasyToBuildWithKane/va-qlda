<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ContractFormModal from '@/modules/contract/components/ContractFormModal.vue';
import ContractDocuments from '@/modules/contract/components/ContractDocuments.vue';
import RenewalQuickModal from '@/modules/contract/components/RenewalQuickModal.vue';
import { useDialog } from '@/composables/useDialog';
import { formatMoney, formatDate, expiryLabel } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    contract: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const c = computed(() => props.contract);
const dialog = useDialog();

const tab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'info' },
    { key: 'finance', label: 'Tài chính', icon: 'budget' },
    { key: 'documents', label: 'Hồ sơ', icon: 'documents' },
    { key: 'renewals', label: 'Gia hạn', icon: 'renewal' },
    { key: 'timeline', label: 'Timeline', icon: 'clock' },
];

const showEdit = ref(false);
const showRenew = ref(false);

async function onDelete() {
    const ok = await dialog.confirm({
        title: 'Xoá hợp đồng?',
        message: `Hợp đồng "${c.value.name}" sẽ được chuyển vào thùng rác.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/${c.value.id}`);
}

const overviewRows = computed(() => [
    { label: 'Mã hợp đồng', value: c.value.code },
    { label: 'Nhà cung cấp', value: c.value.vendor?.name ?? '—' },
    { label: 'Nhóm dịch vụ', value: c.value.category?.name ?? '—' },
    { label: 'Đơn vị sử dụng', value: c.value.using_unit ?? '—' },
    { label: 'Người phụ trách', value: c.value.owner?.name ?? '—' },
    { label: 'Người quản lý', value: c.value.manager?.name ?? '—' },
]);

const financeRows = computed(() => [
    { label: 'Đơn giá', value: formatMoney(c.value.unit_price, c.value.currency) },
    { label: 'Chi phí tháng', value: formatMoney(c.value.monthly_cost, c.value.currency) },
    { label: 'Chi phí năm', value: formatMoney(c.value.annual_cost, c.value.currency) },
    { label: 'Chi phí vòng đời', value: formatMoney(c.value.lifecycle_cost, c.value.currency) },
    { label: 'Tình trạng thanh toán', value: c.value.payment_status?.label ?? '—' },
]);

const termRows = computed(() => [
    { label: 'Ngày ký', value: formatDate(c.value.signed_date) },
    { label: 'Ngày hiệu lực', value: formatDate(c.value.effective_date) },
    { label: 'Ngày hết hạn', value: formatDate(c.value.expiry_date) },
    { label: 'Còn lại', value: expiryLabel(c.value.days_until_expiry) },
    { label: 'Tự động gia hạn', value: c.value.auto_renew ? 'Có' : 'Không' },
]);
</script>

<template>
  <Head :title="c.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="c.name"
        :subtitle="c.code"
        icon="contract"
        icon-color="brand"
        back-href="/contracts"
      >
        <Badge
          :label="c.status.label"
          :color="c.status.color"
        />
        <button
          v-if="c.can?.update"
          type="button"
          class="btn-ghost"
          @click="showRenew = true"
        >
          <AppIcon
            name="renewal"
            :size="15"
          /> Gia hạn
        </button>
        <button
          v-if="c.can?.update"
          type="button"
          class="btn-ghost"
          @click="showEdit = true"
        >
          <AppIcon
            name="edit"
            :size="15"
          /> Sửa
        </button>
        <button
          v-if="c.can?.delete"
          type="button"
          class="btn-ghost text-rose-600"
          @click="onDelete"
        >
          <AppIcon
            name="delete"
            :size="15"
          /> Xoá
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-5xl px-4 py-5">
      <!-- Tabs -->
      <div class="mb-4 flex gap-1 border-b border-slate-200">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
          :class="tab === t.key ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="tab = t.key"
        >
          <AppIcon
            :name="t.icon"
            :size="15"
          /> {{ t.label }}
        </button>
      </div>

      <!-- Overview -->
      <div
        v-if="tab === 'overview'"
        class="grid gap-4 sm:grid-cols-2"
      >
        <section class="card p-4">
          <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
            Thông tin chung
          </h3>
          <dl class="space-y-2">
            <div
              v-for="row in overviewRows"
              :key="row.label"
              class="flex justify-between gap-4 text-sm"
            >
              <dt class="text-slate-400">
                {{ row.label }}
              </dt>
              <dd class="text-right font-medium text-slate-700">
                {{ row.value }}
              </dd>
            </div>
          </dl>
        </section>
        <section class="card p-4">
          <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
            Thời hạn
          </h3>
          <dl class="space-y-2">
            <div
              v-for="row in termRows"
              :key="row.label"
              class="flex justify-between gap-4 text-sm"
            >
              <dt class="text-slate-400">
                {{ row.label }}
              </dt>
              <dd class="text-right font-medium text-slate-700">
                {{ row.value }}
              </dd>
            </div>
          </dl>
        </section>
        <section
          v-if="c.description"
          class="card p-4 sm:col-span-2"
        >
          <h3 class="mb-2 font-display text-sm font-semibold text-slate-800">
            Mô tả
          </h3>
          <p class="whitespace-pre-line text-sm text-slate-600">
            {{ c.description }}
          </p>
        </section>
      </div>

      <!-- Finance -->
      <div
        v-else-if="tab === 'finance'"
        class="card p-4"
      >
        <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
          Tài chính
        </h3>
        <dl class="grid gap-2 sm:grid-cols-2">
          <div
            v-for="row in financeRows"
            :key="row.label"
            class="flex justify-between gap-4 text-sm"
          >
            <dt class="text-slate-400">
              {{ row.label }}
            </dt>
            <dd class="text-right font-medium text-slate-700">
              {{ row.value }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Documents -->
      <div
        v-else-if="tab === 'documents'"
        class="card p-4"
      >
        <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
          Hồ sơ hợp đồng
        </h3>
        <ContractDocuments
          :contract-id="c.id"
          :attachments="c.attachments || []"
          :categories="options.attachmentCategories || []"
          :can-manage="!!c.can?.update"
        />
      </div>

      <!-- Renewals -->
      <div
        v-else-if="tab === 'renewals'"
        class="card p-4"
      >
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-display text-sm font-semibold text-slate-800">
            Lịch sử gia hạn
          </h3>
          <button
            v-if="c.can?.update"
            type="button"
            class="btn-primary text-xs"
            @click="showRenew = true"
          >
            <AppIcon
              name="renewal"
              :size="13"
            /> Gia hạn
          </button>
        </div>
        <ul
          v-if="c.renewals?.length"
          class="divide-y divide-slate-100"
        >
          <li
            v-for="r in c.renewals"
            :key="r.id"
            class="py-2.5"
          >
            <div class="flex items-center justify-between text-sm">
              <span class="text-slate-700">
                {{ formatDate(r.previous_expiry) }} → <strong>{{ formatDate(r.new_expiry) }}</strong>
              </span>
              <span class="text-xs text-slate-400">{{ new Date(r.created_at).toLocaleDateString('vi-VN') }}</span>
            </div>
            <p
              v-if="r.previous_cost || r.new_cost"
              class="mt-0.5 text-xs text-slate-500"
            >
              Chi phí: {{ formatMoney(r.previous_cost, c.currency) }} → {{ formatMoney(r.new_cost, c.currency) }}
            </p>
            <p
              v-if="r.note"
              class="mt-0.5 text-xs text-slate-400"
            >
              {{ r.note }}
            </p>
          </li>
        </ul>
        <p
          v-else
          class="rounded-lg border border-dashed border-slate-200 py-8 text-center text-sm text-slate-400"
        >
          Chưa có lần gia hạn nào.
        </p>
      </div>

      <!-- Timeline -->
      <div
        v-else
        class="card p-4"
      >
        <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">
          Lịch sử hoạt động
        </h3>
        <ol
          v-if="c.activities?.length"
          class="relative space-y-3 border-l border-slate-200 pl-4"
        >
          <li
            v-for="a in c.activities"
            :key="a.id"
            class="relative"
          >
            <span class="absolute -left-[1.30rem] top-1 grid h-3 w-3 place-items-center rounded-full bg-brand/20">
              <span class="h-1.5 w-1.5 rounded-full bg-brand" />
            </span>
            <p class="text-sm text-slate-700">
              {{ a.description }}
            </p>
            <p class="text-xs text-slate-400">
              {{ new Date(a.created_at).toLocaleString('vi-VN') }}
            </p>
          </li>
        </ol>
        <p
          v-else
          class="py-6 text-center text-sm text-slate-400"
        >
          Chưa có hoạt động.
        </p>
      </div>
    </div>

    <ContractFormModal
      :show="showEdit"
      :contract="c"
      :vendors="options.vendors || []"
      :categories="[]"
      :employees="options.employees || []"
      :departments="options.departments || []"
      :status-options="options.status || []"
      :payment-options="options.paymentStatus || []"
      @close="showEdit = false"
      @saved="() => router.reload({ only: ['contract'] })"
    />

    <RenewalQuickModal
      :show="showRenew"
      :contract="c"
      @close="showRenew = false"
      @saved="() => router.reload({ only: ['contract'] })"
    />
  </AppLayout>
</template>
