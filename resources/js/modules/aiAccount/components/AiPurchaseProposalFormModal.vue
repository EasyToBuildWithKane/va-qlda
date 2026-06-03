<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';

const props = defineProps({
    show: Boolean,
    options: { type: Object, required: true },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const form = reactive({
    tool_name: '',
    group_function: 'DEV',
    license_type: 'Pro',
    cost_amount: '',
    cost_unit: 'monthly',
    seats: '',
    justification: '',
});

const costPreviewAmount = computed(() => {
    const n = parseInt(String(form.cost_amount).replace(/\D/g, ''), 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
});

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    Object.assign(form, {
        tool_name: '',
        group_function: 'DEV',
        license_type: 'Pro',
        cost_amount: '',
        cost_unit: 'monthly',
        seats: '',
        justification: '',
    });
});

function onInput() {
    dirty.value = true;
}

function handleSubmit() {
    emit('submit', {
        tool_name: form.tool_name.trim(),
        group_function: form.group_function,
        license_type: form.license_type.trim(),
        cost_amount: parseInt(form.cost_amount, 10),
        cost_unit: form.cost_unit,
        seats: form.seats ? parseInt(form.seats, 10) : null,
        justification: form.justification.trim(),
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Đề xuất mua tài khoản AI"
    max-width="max-w-4xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <form
      class="grid grid-cols-1 gap-5 sm:grid-cols-2"
      @submit.prevent="handleSubmit"
    >
      <div class="min-w-0 sm:col-span-2">
        <label class="label flex items-center gap-1">
          Công cụ đề xuất <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Tên dịch vụ AI cần mua mới (chưa có trong danh sách hiện tại)."
          />
        </label>
        <input
          v-model="form.tool_name"
          type="text"
          required
          class="input w-full"
          placeholder="VD: Cursor Pro, Midjourney Team"
          @input="onInput"
        >
      </div>

      <div class="min-w-0">
        <label class="label">Nhóm chức năng <span class="text-danger">*</span></label>
        <select
          v-model="form.group_function"
          required
          class="input w-full"
          @change="onInput"
        >
          <option
            v-for="o in options.group_function"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
      </div>

      <div class="min-w-0">
        <label class="label">Loại license <span class="text-danger">*</span></label>
        <input
          v-model="form.license_type"
          type="text"
          required
          list="proposal-license-types"
          class="input w-full"
          placeholder="VD: Pro, Team"
          @input="onInput"
        >
        <datalist id="proposal-license-types">
          <option
            v-for="t in options.license_types"
            :key="t"
            :value="t"
          />
        </datalist>
      </div>

      <div class="min-w-0">
        <label class="label">Chi phí dự kiến (VNĐ) <span class="text-danger">*</span></label>
        <input
          v-model="form.cost_amount"
          type="number"
          min="1"
          required
          class="input w-full"
          placeholder="1000000"
          @input="onInput"
        >
        <p
          v-if="costPreviewAmount"
          class="mt-1.5 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs"
        >
          <VndAmount
            :amount="costPreviewAmount"
            inline
          />
        </p>
      </div>

      <div class="min-w-0">
        <label class="label">Chu kỳ <span class="text-danger">*</span></label>
        <select
          v-model="form.cost_unit"
          required
          class="input w-full"
          @change="onInput"
        >
          <option
            v-for="o in options.cost_unit"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
      </div>

      <div class="min-w-0 sm:col-span-2">
        <label class="label flex items-center gap-1">
          Lý do đề xuất <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Mô tả nhu cầu, lợi ích, phạm vi sử dụng — dùng khi quản trị duyệt hoặc từ chối."
          />
        </label>
        <textarea
          v-model="form.justification"
          rows="4"
          required
          minlength="10"
          class="input w-full"
          placeholder="VD: Team DEV cần license cho dự án X, dự kiến 15 người dùng, thay thế bản trial…"
          @input="onInput"
        />
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 sm:col-span-2">
        <button
          type="button"
          class="btn-secondary"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
        >
          Gửi đề xuất
        </button>
      </div>
    </form>
  </Modal>
</template>
