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
        notes: v?.notes ?? '',
        is_active: v?.is_active ?? true,
    });
    form.reset();
});

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
    max-width="max-w-4xl"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <p class="text-[11px] text-slate-500">
        Tạo hồ sơ nhà cung cấp trước. Sau khi lưu, bạn có thể
        <span class="font-medium text-brand">đánh giá NCC trên 6 tiêu chí</span> từ danh sách.
      </p>

      <div class="grid gap-x-6 gap-y-4 lg:grid-cols-2">
        <!-- Cột trái -->
        <div class="space-y-4">
          <div>
            <label class="label">Tên nhà cung cấp *</label>
            <input
              v-model="form.name"
              class="input"
              placeholder="VD: Công ty TNHH Công nghệ ABC"
              title="Tên đầy đủ của nhà cung cấp dịch vụ/phần mềm"
            >
            <p class="mt-1 text-[11px] text-slate-400">
              Tên pháp lý hoặc thương hiệu của NCC.
            </p>
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
              placeholder="VD: 0312345678"
              title="Mã số thuế dùng để đối chiếu hoá đơn"
            >
          </div>

          <div>
            <label class="label">Người liên hệ</label>
            <input
              v-model="form.contact_name"
              class="input"
              placeholder="VD: Nguyễn Văn A — Sales"
              title="Đầu mối liên hệ chính phía NCC"
            >
          </div>

          <div>
            <label class="label">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="input"
              placeholder="sales@nhacungcap.vn"
              title="Email liên hệ / gửi báo giá"
            >
            <p
              v-if="form.errors.email"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.email }}
            </p>
          </div>
        </div>

        <!-- Cột phải -->
        <div class="space-y-4">
          <div>
            <label class="label">Điện thoại</label>
            <input
              v-model="form.phone"
              class="input"
              placeholder="VD: 028 1234 5678"
              title="Số điện thoại liên hệ"
            >
          </div>

          <div>
            <label class="label">Website</label>
            <input
              v-model="form.website"
              class="input"
              placeholder="https://nhacungcap.vn"
              title="Trang web chính thức của NCC"
            >
          </div>

          <div>
            <label class="label">Địa chỉ</label>
            <input
              v-model="form.address"
              class="input"
              placeholder="Số nhà, đường, quận/huyện, tỉnh/thành"
              title="Địa chỉ trụ sở / xuất hoá đơn"
            >
          </div>

          <div>
            <label class="label">Ghi chú</label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="input"
              placeholder="Điều khoản đặc biệt, lịch sử hợp tác, lưu ý…"
              title="Thông tin bổ sung về NCC"
            />
          </div>

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
          {{ form.processing ? 'Đang lưu…' : (isEdit ? 'Lưu' : 'Thêm & tiếp tục đánh giá') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
