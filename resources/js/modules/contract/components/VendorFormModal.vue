<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    vendor: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => !!props.vendor?.id);

const form = useForm({
    name: '',
    tax_code: '',
    contact_name: '',
    email: '',
    phone: '',
    website: '',
    address: '',
    rating: null,
    notes: '',
    is_active: true,
});

watch(() => props.show, (open) => {
    if (!open) return;
    const v = props.vendor;
    form.clearErrors();
    form.defaults({
        name: v?.name ?? '',
        tax_code: v?.tax_code ?? '',
        contact_name: v?.contact_name ?? '',
        email: v?.email ?? '',
        phone: v?.phone ?? '',
        website: v?.website ?? '',
        address: v?.address ?? '',
        rating: v?.rating ?? null,
        notes: v?.notes ?? '',
        is_active: v?.is_active ?? true,
    });
    form.reset();
});

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (isEdit.value) {
        form.put(`/contracts/vendors/${props.vendor.id}`, opts);
    } else {
        form.post('/contracts/vendors', opts);
    }
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp'"
    max-width="max-w-2xl"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label">Tên nhà cung cấp *</label>
          <input
            v-model="form.name"
            class="input"
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.name }}
          </p>
        </div>
        <div>
          <label class="label">Mã số thuế</label>
          <input
            v-model="form.tax_code"
            class="input"
          >
        </div>
        <div>
          <label class="label">Người liên hệ</label>
          <input
            v-model="form.contact_name"
            class="input"
          >
        </div>
        <div>
          <label class="label">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="input"
          >
          <p
            v-if="form.errors.email"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.email }}
          </p>
        </div>
        <div>
          <label class="label">Điện thoại</label>
          <input
            v-model="form.phone"
            class="input"
          >
        </div>
        <div>
          <label class="label">Website</label>
          <input
            v-model="form.website"
            class="input"
          >
        </div>
        <div>
          <label class="label">Đánh giá (1–5)</label>
          <input
            v-model="form.rating"
            type="number"
            min="1"
            max="5"
            class="input"
          >
        </div>
        <div class="sm:col-span-2">
          <label class="label">Địa chỉ</label>
          <input
            v-model="form.address"
            class="input"
          >
        </div>
        <div class="sm:col-span-2">
          <label class="label">Ghi chú</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="input"
          />
        </div>
        <div>
          <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            Đang hợp tác
          </label>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
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
          {{ form.processing ? 'Đang lưu…' : (isEdit ? 'Lưu' : 'Thêm') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
