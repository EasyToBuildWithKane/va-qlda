<script setup>
import { computed, provide, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import VendorFormFields from '@/modules/contract/components/VendorFormFields.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    vendor: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => !!props.vendor?.id);

function resolveCategoryIds(v) {
    if (Array.isArray(v?.category_ids) && v.category_ids.length) {
        return v.category_ids.map((id) => Number(id));
    }
    if (Array.isArray(v?.service_categories)) {
        return v.service_categories.map((c) => Number(c.id)).filter((id) => !Number.isNaN(id));
    }
    return [];
}

const form = useForm({
    name: '',
    tax_code: '',
    contact_name: '',
    email: '',
    phone: '',
    website: '',
    address: '',
    notes: '',
    is_active: true,
    category_ids: [],
    category_names: [],
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
        notes: v?.notes ?? '',
        is_active: v?.is_active ?? true,
        category_ids: resolveCategoryIds(v),
        category_names: [],
    });
    form.reset();
});

provide('vendorForm', form);

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    };
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
    max-width="max-w-5xl"
    fit-viewport
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="flex min-h-0 flex-1 flex-col overflow-hidden"
      @submit.prevent="submit"
    >
      <p class="mb-3 shrink-0 text-[11px] leading-relaxed text-slate-500">
        Trường có dấu
        <span class="font-medium text-danger">*</span>
        là bắt buộc — bấm
        <span class="font-medium text-slate-600">ⓘ</span>
        cạnh nhãn để xem gợi ý.
        <template v-if="!isEdit">
          Sau khi lưu có thể
          <span class="font-medium text-brand">đánh giá NCC trên 6 tiêu chí</span>.
        </template>
      </p>

      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-0.5 [-webkit-overflow-scrolling:touch]">
        <VendorFormFields
          :vendor="vendor"
          :categories="categories"
        />
      </div>

      <div class="mt-3 flex shrink-0 justify-end gap-2 border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost h-9"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Đang lưu…' : (isEdit ? 'Lưu thay đổi' : 'Thêm & tiếp tục đánh giá') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
