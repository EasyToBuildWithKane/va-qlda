<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import EmployeeAutocomplete from './EmployeeAutocomplete.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    contract: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    billingOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => !!props.contract?.id);

const form = useForm({
    code: '',
    name: '',
    vendor_id: null,
    category_id: null,
    using_unit: '',
    owner_id: null,
    manager_id: null,
    billing_cycle: null,
    effective_date: '',
    expiry_date: '',
    status: 'active',
    description: '',
    links_text: '',
});

watch(() => props.show, (open) => {
    if (!open) return;
    const c = props.contract;
    form.clearErrors();
    form.defaults({
        code: c?.code ?? '',
        name: c?.name ?? '',
        vendor_id: c?.vendor_id ?? null,
        category_id: c?.category_id ?? null,
        using_unit: c?.using_unit ?? '',
        owner_id: c?.owner?.id ?? null,
        manager_id: c?.manager?.id ?? null,
        billing_cycle: c?.billing_cycle?.value ?? null,
        effective_date: c?.effective_date ?? '',
        expiry_date: c?.expiry_date ?? '',
        status: c?.status?.value ?? 'active',
        description: c?.description ?? '',
        links_text: '',
    });
    form.reset();
});

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

function submit() {
    const links = String(form.links_text ?? '')
        .split(/[\n;,]+/)
        .map((s) => s.trim())
        .filter(Boolean);

    form.transform((data) => ({
        ...data,
        links: isEdit.value ? [] : links,
        links_text: undefined,
    }));

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
    max-width="max-w-2xl"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Mã HĐ</label>
          <input
            v-model="form.code"
            class="input"
            placeholder="VD: KON-VM-01"
            :readonly="isEdit"
            :class="{ 'bg-slate-50 text-slate-600': isEdit }"
          >
          <p
            v-if="!isEdit"
            class="mt-1 text-[11px] text-slate-400"
          >
            Để trống hệ thống tự sinh mã.
          </p>
        </div>
        <div>
          <label class="label">Tên DV *</label>
          <input
            v-model="form.name"
            class="input"
            placeholder="VD: Kidsonline"
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.name }}
          </p>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Tên NCC</label>
          <select
            v-model="form.vendor_id"
            class="input"
          >
            <option :value="null">
              Chưa chọn NCC
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
          <label class="label">Nhóm DV</label>
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
        <label class="label">Phòng ban</label>
        <input
          v-model="form.using_unit"
          class="input"
          placeholder="VD: Mầm non Bình Thới"
        >
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Người phụ trách</label>
          <EmployeeAutocomplete
            v-model="form.owner_id"
            :options="employees"
            placeholder="Email hoặc tên…"
          />
        </div>
        <div>
          <label class="label">Người quản lý</label>
          <EmployeeAutocomplete
            v-model="form.manager_id"
            :options="employees"
            placeholder="Email hoặc tên…"
          />
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Ngày bắt đầu</label>
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
          <label class="label">Chu kỳ</label>
          <select
            v-model="form.billing_cycle"
            class="input"
          >
            <option :value="null">
              Chưa chọn
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
      </div>

      <div v-if="!isEdit">
        <label class="label">Link file</label>
        <textarea
          v-model="form.links_text"
          rows="2"
          class="input font-mono text-xs"
          placeholder="Mỗi dòng một tên file hoặc URL"
        />
      </div>

      <div>
        <label class="label">Ghi chú</label>
        <textarea
          v-model="form.description"
          rows="2"
          class="input"
          placeholder="VD: HCQT mua"
        />
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
