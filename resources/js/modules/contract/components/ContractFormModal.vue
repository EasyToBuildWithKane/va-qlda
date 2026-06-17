<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import MoneyInput from './MoneyInput.vue';
import EmployeeAutocomplete from './EmployeeAutocomplete.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    contract: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    contracts: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    paymentOptions: { type: Array, default: () => [] },
    billingOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => !!props.contract?.id);

const form = useForm({
    name: '',
    vendor_id: null,
    category_id: null,
    root_contract_id: null,
    department_id: null,
    using_unit: '',
    owner_id: null,
    manager_id: null,
    currency: 'VND',
    unit_price: null,
    monthly_cost: null,
    annual_cost: null,
    lifecycle_cost: null,
    payment_status: 'unpaid',
    billing_cycle: null,
    signed_date: '',
    effective_date: '',
    expiry_date: '',
    auto_renew: false,
    renewal_term_months: null,
    notice_period_days: null,
    status: 'draft',
    description: '',
});

watch(() => props.show, (open) => {
    if (!open) return;
    const c = props.contract;
    form.clearErrors();
    form.defaults({
        name: c?.name ?? '',
        vendor_id: c?.vendor_id ?? null,
        category_id: c?.category_id ?? null,
        root_contract_id: c?.root_contract_id ?? null,
        department_id: c?.department_id ?? null,
        using_unit: c?.using_unit ?? '',
        owner_id: c?.owner?.id ?? null,
        manager_id: c?.manager?.id ?? null,
        currency: c?.currency ?? 'VND',
        unit_price: c?.unit_price ?? null,
        monthly_cost: c?.monthly_cost ?? null,
        annual_cost: c?.annual_cost ?? null,
        lifecycle_cost: c?.lifecycle_cost ?? null,
        payment_status: c?.payment_status?.value ?? 'unpaid',
        billing_cycle: c?.billing_cycle?.value ?? null,
        signed_date: c?.signed_date ?? '',
        effective_date: c?.effective_date ?? '',
        expiry_date: c?.expiry_date ?? '',
        auto_renew: c?.auto_renew ?? false,
        renewal_term_months: c?.renewal_term_months ?? null,
        notice_period_days: c?.notice_period_days ?? null,
        status: c?.status?.value ?? 'draft',
        description: c?.description ?? '',
    });
    form.reset();
});

// Nhóm dịch vụ — danh mục chung (Nhóm DV), không lọc theo NCC.
const serviceGroupOptions = computed(() => {
    const byName = new Map();
    for (const c of props.categories) {
        if (!byName.has(c.name)) byName.set(c.name, c);
    }
    return [...byName.values()].sort((a, b) => {
        const order = (a.sort_order ?? 0) - (b.sort_order ?? 0);
        if (order !== 0) return order;
        return String(a.name).localeCompare(String(b.name), 'vi');
    });
});

// Hợp đồng gốc khả dụng: cùng NCC, không phải chính nó.
const rootCandidates = computed(() => props.contracts.filter((c) => {
    if (isEdit.value && c.id === props.contract.id) return false;
    if (form.vendor_id && c.vendor_id && c.vendor_id !== form.vendor_id) return false;
    return true;
}));

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    };
    if (isEdit.value) {
        form.put(`/contracts/${props.contract.id}`, opts);
    } else {
        form.post('/contracts', opts);
    }
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa hợp đồng' : 'Tạo hợp đồng'"
    max-width="max-w-5xl"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <div class="grid gap-x-6 gap-y-4 lg:grid-cols-2">
        <!-- ── Cột trái: Thông tin chung ── -->
        <div class="space-y-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-brand/80">
            Thông tin chung
          </p>

          <div>
            <label class="label">Tên hợp đồng *</label>
            <input
              v-model="form.name"
              class="input"
              placeholder="VD: Microsoft 365 E3 (50 license)"
            >
            <p
              v-if="form.errors.name"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.name }}
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Nhà cung cấp</label>
              <select
                v-model="form.vendor_id"
                class="input"
              >
                <option :value="null">
                  — Chọn NCC —
                </option>
                <option
                  v-for="v in vendors"
                  :key="v.id"
                  :value="v.id"
                >
                  {{ v.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Nhóm dịch vụ</label>
              <select
                v-model="form.category_id"
                class="input"
              >
                <option :value="null">
                  Chưa chọn nhóm
                </option>
                <option
                  v-for="c in serviceGroupOptions"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
            </div>
          </div>

          <div>
            <label class="label">Thuộc hợp đồng gốc</label>
            <select
              v-model="form.root_contract_id"
              class="input"
            >
              <option :value="null">
                — Đây là hợp đồng gốc —
              </option>
              <option
                v-for="c in rootCandidates"
                :key="c.id"
                :value="c.id"
              >
                {{ c.code }} · {{ c.name }}
              </option>
            </select>
            <p class="mt-1 text-[11px] text-slate-400">
              Chọn nếu đây là bản gia hạn / phụ lục của một bộ hợp đồng.
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Đơn vị sử dụng</label>
              <input
                v-model="form.using_unit"
                class="input"
                placeholder="VD: Toàn trường"
              >
            </div>
            <div>
              <label class="label">Phòng ban</label>
              <select
                v-model="form.department_id"
                class="input"
              >
                <option :value="null">
                  — Không —
                </option>
                <option
                  v-for="d in departments"
                  :key="d.id"
                  :value="d.id"
                >
                  {{ d.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Người phụ trách</label>
              <EmployeeAutocomplete
                v-model="form.owner_id"
                :options="employees"
                placeholder="Gõ tên hoặc email…"
              />
            </div>
            <div>
              <label class="label">Người quản lý</label>
              <EmployeeAutocomplete
                v-model="form.manager_id"
                :options="employees"
                placeholder="Gõ tên hoặc email…"
              />
            </div>
          </div>

          <div>
            <label class="label">Mô tả</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="input"
              placeholder="Ghi chú nội dung, phạm vi hợp đồng…"
            />
          </div>
        </div>

        <!-- ── Cột phải: Tài chính & thời hạn ── -->
        <div class="space-y-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-brand/80">
            Tài chính & thời hạn
          </p>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Chi phí tháng</label>
              <MoneyInput v-model="form.monthly_cost" />
            </div>
            <div>
              <label class="label">Chi phí năm</label>
              <MoneyInput v-model="form.annual_cost" />
            </div>
            <div>
              <label class="label">Chi phí vòng đời</label>
              <MoneyInput v-model="form.lifecycle_cost" />
            </div>
            <div>
              <label class="label">Đơn giá</label>
              <MoneyInput v-model="form.unit_price" />
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Tình trạng thanh toán</label>
              <select
                v-model="form.payment_status"
                class="input"
              >
                <option
                  v-for="o in paymentOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Chu kỳ</label>
              <select
                v-model="form.billing_cycle"
                class="input"
              >
                <option :value="null">
                  — Không —
                </option>
                <option
                  v-for="o in billingOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <div>
              <label class="label">Ngày ký</label>
              <input
                v-model="form.signed_date"
                type="date"
                class="input"
              >
            </div>
            <div>
              <label class="label">Ngày hiệu lực</label>
              <input
                v-model="form.effective_date"
                type="date"
                class="input"
              >
            </div>
            <div>
              <label class="label">Ngày hết hạn</label>
              <input
                v-model="form.expiry_date"
                type="date"
                class="input"
              >
              <p
                v-if="form.errors.expiry_date"
                class="mt-1 text-xs text-rose-600"
              >
                {{ form.errors.expiry_date }}
              </p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Trạng thái</label>
              <select
                v-model="form.status"
                class="input"
              >
                <option
                  v-for="o in statusOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Kỳ gia hạn (tháng)</label>
              <input
                v-model="form.renewal_term_months"
                type="number"
                min="0"
                class="input"
              >
            </div>
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input
              v-model="form.auto_renew"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            Tự động gia hạn
          </label>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Đang lưu…' : (isEdit ? 'Lưu thay đổi' : 'Tạo hợp đồng') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
