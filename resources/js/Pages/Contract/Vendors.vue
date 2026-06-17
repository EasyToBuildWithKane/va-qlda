<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFormModal from '@/modules/contract/components/VendorFormModal.vue';
import VendorReviewModal from '@/modules/contract/components/VendorReviewModal.vue';
import VendorSummaryBar from '@/modules/contract/components/VendorSummaryBar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';

const props = defineProps({
    vendors: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const toast = useToast();
const page = usePage();

const vendorList = computed(() => props.vendors.data ?? props.vendors ?? []);
const search = ref(props.filters.q ?? '');
const scopeFilter = ref(props.filters.scope ?? '');

let debounce;
function navigateVendors() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/contracts/vendors', {
            q: search.value || undefined,
            scope: scopeFilter.value || undefined,
        }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }, 350);
}
watch(search, navigateVendors);

watch(
    () => props.filters.scope,
    (v) => { scopeFilter.value = v ?? ''; },
);

function onQuickFilter({ scope }) {
    scopeFilter.value = scope ?? '';
    clearTimeout(debounce);
    router.get('/contracts/vendors', {
        q: search.value || undefined,
        scope: scopeFilter.value || undefined,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}

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

// ── Đánh giá NCC ──
const showReview = ref(false);
const reviewing = ref(null);

function openReview(v) {
    reviewing.value = v;
    showReview.value = true;
}

// Sau khi tạo NCC mới → reload rồi mở luôn modal đánh giá (tạo xong mới đánh giá).
function onVendorSaved() {
    const created = page.props.flash?.created_vendor;
    router.reload({
        only: ['vendors', 'summary'],
        onSuccess: () => {
            if (created) {
                reviewing.value = { id: created.id, name: created.name };
                showReview.value = true;
            }
        },
    });
}

function onReviewSaved() {
    router.reload({ only: ['vendors', 'summary'] });
}

function scoreTone(score) {
    if (score == null) return 'bg-slate-100 text-slate-500';
    if (score < 7) return 'bg-rose-100 text-rose-700';
    if (score < 8.5) return 'bg-amber-100 text-amber-700';
    return 'bg-emerald-100 text-emerald-700';
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
        :badge="summary.total ?? null"
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

    <VendorSummaryBar
      :summary="summary"
      :active-scope="scopeFilter"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="flex w-full min-w-0 flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 lg:flex-nowrap">
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

      <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
            <span
              v-if="v.review_score != null"
              class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
              :class="scoreTone(v.review_score)"
              :title="v.is_low_score ? 'Điểm đánh giá dưới 7' : 'Điểm đánh giá gần nhất'"
            >
              {{ v.review_score }}/10
            </span>
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

          <button
            v-if="can.evaluate"
            type="button"
            class="btn-ghost mt-3 w-full justify-center text-xs"
            @click="openReview(v)"
          >
            <AppIcon
              name="performance"
              :size="14"
            />
            {{ v.review_score != null ? 'Đánh giá lại' : 'Đánh giá NCC' }}
          </button>
        </article>

        <p
          v-if="!vendorList.length"
          class="rounded-card border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400 sm:col-span-2 lg:col-span-3 xl:col-span-4"
        >
          Chưa có nhà cung cấp nào.
        </p>
      </div>
    </div>

    <VendorFormModal
      :show="showForm"
      :vendor="editing"
      @close="showForm = false"
      @saved="onVendorSaved"
    />

    <VendorReviewModal
      :show="showReview"
      :vendor="reviewing"
      :criteria="options.criteria || []"
      :recommendation-options="options.recommendation || []"
      @close="showReview = false"
      @saved="onReviewSaved"
    />
  </AppLayout>
</template>
