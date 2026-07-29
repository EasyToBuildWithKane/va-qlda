<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import VendorDetailSummaryBar from '@/modules/contract/components/VendorDetailSummaryBar.vue';
import VendorReviewHistoryPanel from '@/modules/contract/components/VendorReviewHistoryPanel.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import VendorFormModal from '@/modules/contract/components/VendorFormModal.vue';
import VendorReviewModal from '@/modules/contract/components/VendorReviewModal.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { formatMoneyShort, formatDate, expiryLabel } from '@/modules/contract/composables/useContractFormat.js';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    vendor: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

/** Inertia + JsonResource có thể bọc `{ data: … }` nếu backend chưa resolve. */
const vendor = computed(() => {
    const raw = props.vendor;
    return raw?.data ?? raw;
});
const dialog = useDialog();
const toast = useToast();

const tab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'info' },
    { key: 'contracts', label: 'Hợp đồng', icon: 'documents' },
    { key: 'reviews', label: 'Lịch sử đánh giá', icon: 'performance' },
];

const contracts = computed(() => {
    const raw = vendor.value.contracts;
    return raw?.data ?? raw ?? [];
});
const reviews = computed(() => {
    const raw = vendor.value.reviews;
    return raw?.data ?? raw ?? [];
});

const showForm = ref(false);
const showReview = ref(false);
const editingReview = ref(null);

function openReview(review = null) {
    editingReview.value = review;
    showReview.value = true;
}

function closeReview() {
    showReview.value = false;
    editingReview.value = null;
}

function reloadVendor() {
    router.reload({ only: ['vendor'] });
}

async function onDelete() {
    if ((vendor.value.contracts_count ?? 0) > 0) {
        toast.error('Không thể xoá: nhà cung cấp đang có hợp đồng.');
        return;
    }
    const ok = await dialog.confirm({
        title: 'Xoá nhà cung cấp?',
        message: `"${vendor.value.name}" sẽ bị xoá khỏi danh sách.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/vendors/${vendor.value.id}`, {
        onSuccess: () => router.visit('/contracts/vendors'),
    });
}
</script>

<template>
  <Head :title="vendor.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="vendor.name"
        :subtitle="`Mã ${vendor.code}`"
        icon="vendor"
        icon-color="brand"
      >
        <button
          v-if="vendor.can?.evaluate"
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="openReview()"
        >
          <AppIcon
            name="performance"
            :size="15"
          />
          {{ vendor.review_score != null ? 'Đánh giá lại' : 'Đánh giá NCC' }}
        </button>
        <button
          v-if="vendor.can?.update"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="showForm = true"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
          Chỉnh sửa
        </button>
        <button
          v-if="vendor.can?.delete"
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs text-rose-600 hover:bg-rose-50"
          @click="onDelete"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
          Xoá
        </button>
      </PageHeader>
    </template>

    <VendorDetailSummaryBar :vendor="vendor" />

    <nav
      class="mb-5 flex flex-wrap gap-1 border-b border-slate-200 pb-1"
      aria-label="Tab chi tiết nhà cung cấp"
    >
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition"
        :class="tab === t.key ? 'bg-brand/10 text-brand' : 'text-slate-600 hover:bg-slate-100'"
        :aria-current="tab === t.key ? 'page' : undefined"
        @click="tab = t.key"
      >
        <AppIcon
          :name="t.icon"
          :size="14"
        />
        {{ t.label }}
        <span
          v-if="t.key === 'contracts'"
          class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] tabular-nums text-slate-600"
        >
          {{ contracts.length }}
        </span>
        <span
          v-if="t.key === 'reviews'"
          class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] tabular-nums text-slate-600"
        >
          {{ reviews.length }}
        </span>
      </button>
    </nav>

    <div
      v-if="tab === 'overview'"
      class="grid gap-4 lg:grid-cols-2"
    >
      <section class="card p-5">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Thông tin chung
        </h3>
        <dl class="divide-y divide-slate-100">
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Mã NCC"
                tooltip="Mã hệ thống tự sinh — dùng tra cứu và xuất báo cáo."
              />
            </dt>
            <dd class="font-mono text-sm text-slate-800">
              {{ vendor.code }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Tên nhà cung cấp"
                required
                wide
                tooltip="Tên pháp lý hoặc thương hiệu — bắt buộc khi tạo mới."
              />
            </dt>
            <dd class="text-sm font-medium text-slate-800">
              {{ vendor.name }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Mã số thuế"
                tooltip="MST trên hoá đơn và hợp đồng."
              />
            </dt>
            <dd
              class="text-sm"
              :class="vendor.tax_code ? 'text-slate-800' : 'italic text-slate-400'"
            >
              {{ displayOrEmpty(vendor.tax_code, EMPTY_LABELS.notUpdated) }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="card p-5">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Liên hệ
        </h3>
        <dl class="divide-y divide-slate-100">
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Người liên hệ"
                tooltip="Đầu mối chính phía nhà cung cấp."
              />
            </dt>
            <dd
              class="text-sm"
              :class="vendor.contact_name ? 'text-slate-800' : 'italic text-slate-400'"
            >
              {{ displayOrEmpty(vendor.contact_name, EMPTY_LABELS.notUpdated) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Email"
                tooltip="Email gửi báo giá hoặc hỗ trợ."
              />
            </dt>
            <dd class="text-sm">
              <a
                v-if="vendor.email"
                :href="`mailto:${vendor.email}`"
                class="text-brand hover:underline"
              >{{ vendor.email }}</a>
              <span
                v-else
                class="italic text-slate-400"
              >{{ EMPTY_LABELS.notUpdated }}</span>
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Điện thoại"
                tooltip="Số liên hệ trực tiếp."
              />
            </dt>
            <dd
              class="text-sm"
              :class="vendor.phone ? 'text-slate-800' : 'italic text-slate-400'"
            >
              {{ displayOrEmpty(vendor.phone, EMPTY_LABELS.notUpdated) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Website"
                tooltip="Trang web chính thức của NCC."
              />
            </dt>
            <dd class="text-sm">
              <a
                v-if="vendor.website"
                :href="vendor.website.startsWith('http') ? vendor.website : `https://${vendor.website}`"
                target="_blank"
                rel="noopener noreferrer"
                class="break-all text-brand hover:underline"
              >{{ vendor.website }}</a>
              <span
                v-else
                class="italic text-slate-400"
              >{{ EMPTY_LABELS.notUpdated }}</span>
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
            <dt>
              <VendorFieldLabel
                compact
                label="Địa chỉ"
                wide
                tooltip="Trụ sở hoặc địa chỉ trên hợp đồng."
              />
            </dt>
            <dd
              class="text-sm"
              :class="vendor.address ? 'text-slate-800' : 'italic text-slate-400'"
            >
              {{ displayOrEmpty(vendor.address, EMPTY_LABELS.notUpdated) }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="card p-5 lg:col-span-2">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Ghi chú nội bộ
        </h3>
        <VendorFieldLabel
          compact
          label="Ghi chú"
          wide
          tooltip="Thông tin nội bộ về NCC — chỉ hiển thị trong VA-Workspace."
        />
        <p
          class="mt-2 whitespace-pre-wrap text-sm leading-relaxed"
          :class="vendor.notes ? 'text-slate-700' : 'italic text-slate-400'"
        >
          {{ displayOrEmpty(vendor.notes, EMPTY_LABELS.notUpdated) }}
        </p>
      </section>
    </div>

    <div v-else-if="tab === 'contracts'">
      <div class="card overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Hợp đồng
              </th>
              <th class="px-5 py-3">
                Trạng thái
              </th>
              <th class="px-5 py-3 text-right">
                Chi phí / năm
              </th>
              <th class="px-5 py-3">
                Hết hạn
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in contracts"
              :key="c.id"
              class="border-t border-slate-100 hover:bg-slate-50/80"
            >
              <td class="px-5 py-3">
                <Link
                  :href="`/contracts/${c.id}`"
                  class="font-medium text-brand hover:underline"
                >
                  {{ c.name }}
                </Link>
                <p class="font-mono text-xs text-slate-400">
                  {{ c.code }}
                </p>
              </td>
              <td class="px-5 py-3">
                <Badge
                  v-if="c.status?.label"
                  :label="c.status.label"
                  :color="c.status.color ?? 'slate'"
                />
              </td>
              <td class="px-5 py-3 text-right tabular-nums">
                {{ formatMoneyShort(c.annual_cost ?? c.annual_cost_resolved ?? 0) }}
              </td>
              <td class="px-5 py-3 text-xs text-slate-600">
                {{ c.expiry_date ? formatDate(c.expiry_date) : EMPTY_LABELS.period }}
                <span
                  v-if="c.days_until_expiry != null"
                  class="block text-slate-400"
                >
                  {{ expiryLabel(c.days_until_expiry) }}
                </span>
              </td>
            </tr>
            <tr v-if="!contracts.length">
              <td
                colspan="4"
                class="px-5 py-12 text-center text-sm italic text-slate-400"
              >
                Chưa có hợp đồng gắn với NCC này.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <VendorReviewHistoryPanel
      v-else
      :vendor-id="vendor.id"
      :reviews="reviews"
      :criteria="options.criteria || []"
      :recommendation-options="options.recommendation || []"
      :can-evaluate="Boolean(vendor.can?.evaluate)"
      @evaluate="openReview()"
      @edit="openReview"
      @deleted="reloadVendor"
    />

    <VendorFormModal
      :show="showForm"
      :vendor="vendor"
      @close="showForm = false"
      @saved="reloadVendor"
    />

    <VendorReviewModal
      :show="showReview"
      :vendor="vendor"
      :review="editingReview"
      :criteria="options.criteria || []"
      :recommendation-options="options.recommendation || []"
      :employees="options.employees || []"
      @close="closeReview"
      @saved="reloadVendor"
    />
  </AppLayout>
</template>
