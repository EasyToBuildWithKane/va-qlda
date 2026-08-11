<script setup>
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    grant: { type: Object, default: null },
    ownerOptions: { type: Array, default: () => [] },
    permissionOptions: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'save']);

const accountId = ref(null);
const permissions = ref(['view']);

const isEdit = computed(() => Boolean(props.grant?.id));
const title = computed(() => (isEdit.value ? 'Chỉnh sửa quyền truy cập' : 'Thêm người truy cập'));

const dirty = computed(() =>
    isEdit.value
        ? JSON.stringify([...permissions.value].sort()) !== JSON.stringify([...(props.grant?.permissions || [])].sort())
        : accountId.value !== null,
);

const accountSelectOptions = computed(() =>
    props.ownerOptions.map((o) => ({
        id: o.id,
        display_name: o.display_name,
        username: o.username,
        label: `${o.display_name} (${o.username})`,
    })),
);

function resetForm() {
    if (props.grant) {
        accountId.value = props.grant.account_id ?? props.grant.account?.id ?? null;
        permissions.value = [...(props.grant.permissions || ['view'])];
    } else {
        accountId.value = null;
        permissions.value = ['view'];
    }
}

watch(
    () => [props.show, props.grant],
    () => {
        if (props.show) resetForm();
    },
    { immediate: true },
);

function togglePerm(value) {
    const set = new Set(permissions.value);
    if (set.has(value)) set.delete(value);
    else set.add(value);
    if (!set.has('view') && set.size > 0) set.add('view');
    permissions.value = [...set];
}

function submit() {
    if (!accountId.value || !permissions.value.length) return;
    emit('save', {
        account_id: Number(accountId.value),
        permissions: permissions.value,
    });
}
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="max-w-2xl"
    :dirty="dirty"
    close-confirm-title="Đóng form cấp quyền?"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <div>
        <span class="mb-1 block text-xs font-medium text-slate-600">
          Tài khoản hệ thống <span class="text-danger">*</span>
        </span>
        <SearchSelect
          id="ai-grant-account"
          v-model="accountId"
          :options="accountSelectOptions"
          value-key="id"
          label-key="label"
          :search-keys="['display_name', 'username', 'label']"
          placeholder="Tìm theo tên hoặc username…"
          search-placeholder="Nhập để lọc danh sách…"
          :disabled="isEdit"
          panel-z-index="120"
          class="w-full"
        />
      </div>

      <div>
        <span class="mb-1.5 block text-xs font-medium text-slate-600">
          Quyền được cấp <span class="text-danger">*</span>
        </span>
        <div class="grid gap-2 sm:grid-cols-2">
          <label
            v-for="p in permissionOptions"
            :key="p.value"
            class="flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm transition"
            :class="permissions.includes(p.value)
              ? 'border-brand/40 bg-brand/5 text-slate-800'
              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
          >
            <input
              type="checkbox"
              class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
              :checked="permissions.includes(p.value)"
              @change="togglePerm(p.value)"
            >
            <span class="font-medium">{{ p.label }}</span>
          </label>
        </div>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
      <button
        type="button"
        class="btn-ghost h-9 px-3 text-xs"
        :disabled="saving"
        @click="emit('close')"
      >
        Huỷ
      </button>
      <button
        type="button"
        class="btn-primary h-9 px-4 text-xs"
        :disabled="saving || !accountId || !permissions.length"
        @click="submit"
      >
        {{ isEdit ? 'Lưu thay đổi' : 'Cấp quyền' }}
      </button>
    </div>
  </Modal>
</template>
