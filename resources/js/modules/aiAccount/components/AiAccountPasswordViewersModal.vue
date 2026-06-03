<script setup>
import { watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import PasswordViewerAccountPick from '@/modules/aiAccount/components/PasswordViewerAccountPick.vue';
import { useAiPasswordViewers } from '@/modules/aiAccount/composables/useAiPasswordViewers';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const {
    loading,
    saving,
    viewers,
    candidates,
    load,
    addViewer,
    removeViewer,
} = useAiPasswordViewers();

watch(
    () => props.show,
    (open) => {
        if (open) load();
    },
);

async function onPick(candidate) {
    if (!candidate?.id || saving.value) return;
    try {
        await addViewer(candidate.id);
    } catch {
        /* toast handled */
    }
}

async function onRemove(row) {
    if (saving.value) return;
    await removeViewer(row.id, row.name);
}
</script>

<template>
  <Modal
    :show="show"
    title="Thành viên được xem mật khẩu"
    max-width="max-w-2xl"
    @close="emit('close')"
  >
    <p class="mb-4 text-sm text-slate-600">
      Chỉ <strong>quản trị viên</strong> và các thành viên trong danh sách dưới đây mới thấy mật khẩu đăng nhập
      khi mở form Sửa tài khoản AI.
    </p>

    <PasswordViewerAccountPick
      :candidates="candidates"
      :disabled="loading || saving || !candidates.length"
      @pick="onPick"
    />
    <p
      v-if="!loading && !candidates.length"
      class="mt-1 text-xs text-slate-500"
    >
      Đã thêm tất cả tài khoản đăng nhập khả dụng.
    </p>

    <div class="mt-5 border-t border-slate-100 pt-4">
      <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
        Đang được phép ({{ viewers.length }})
      </h3>

      <div
        v-if="loading"
        class="py-8 text-center text-sm text-slate-500"
      >
        Đang tải…
      </div>

      <ul
        v-else-if="viewers.length"
        class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200"
      >
        <li
          v-for="row in viewers"
          :key="row.id"
          class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
        >
          <div class="min-w-0">
            <p class="font-medium text-slate-800">
              {{ row.name }}
            </p>
            <p class="text-xs text-slate-500">
              {{ row.email }}
              <span v-if="row.department"> · {{ row.department }}</span>
              <span class="text-slate-400"> · {{ row.role_label }}</span>
            </p>
            <p
              v-if="row.granted_at"
              class="mt-0.5 text-[10px] text-slate-400"
            >
              Cấp quyền {{ row.granted_at }}
              <template v-if="row.granted_by_name">
                · bởi {{ row.granted_by_name }}
              </template>
            </p>
          </div>
          <button
            type="button"
            class="btn-ghost shrink-0 px-2 py-1 text-xs text-rose-600"
            :disabled="saving"
            @click="onRemove(row)"
          >
            Thu hồi
          </button>
        </li>
      </ul>

      <p
        v-else
        class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
      >
        Chưa có thành viên nào ngoài quản trị viên.
      </p>
    </div>

    <div class="mt-5 flex justify-end border-t border-slate-100 pt-4">
      <button
        type="button"
        class="btn-secondary"
        @click="emit('close')"
      >
        Đóng
      </button>
    </div>
  </Modal>
</template>
