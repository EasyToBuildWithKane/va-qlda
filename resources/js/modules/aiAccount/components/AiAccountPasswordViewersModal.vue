<script setup>
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import PasswordViewerAccountPick from '@/modules/aiAccount/components/PasswordViewerAccountPick.vue';
import { useAiPasswordViewers } from '@/modules/aiAccount/composables/useAiPasswordViewers';

const props = defineProps({
    show: Boolean,
    /** Danh sách tài khoản AI để chọn công cụ */
    accounts: { type: Array, default: () => [] },
    /** Mở modal với tài khoản đã chọn (từ menu Thao tác) */
    initialAccountId: { type: String, default: null },
});

const emit = defineEmits(['close']);

const selectedId = ref(null);

const {
    loading,
    saving,
    viewers,
    candidates,
    selectedAccount,
    load,
    addViewer,
    removeViewer,
} = useAiPasswordViewers();

const accountOptions = computed(() =>
    (props.accounts ?? []).map((a) => ({
        id: a.id,
        label: [a.tool_name, a.email_registered].filter(Boolean).join(' · '),
    })),
);

const selectedLabel = computed(() => {
    const opt = accountOptions.value.find((o) => o.id === selectedId.value);
    return opt?.label ?? '';
});

watch(
    () => props.show,
    (open) => {
        if (!open) {
            selectedId.value = null;
            return;
        }
        selectedId.value = props.initialAccountId
            ?? accountOptions.value[0]?.id
            ?? null;
        if (selectedId.value) {
            load(selectedId.value);
        }
    },
);

watch(selectedId, (id) => {
    if (props.show && id) {
        load(id);
    }
});

async function onPick(candidate) {
    if (!candidate?.id || !selectedId.value || saving.value) return;
    try {
        await addViewer(selectedId.value, candidate.id);
    } catch {
        /* toast handled */
    }
}

async function onRemove(row) {
    if (saving.value || !selectedId.value) return;
    await removeViewer(row.id, row.name, selectedId.value);
}
</script>

<template>
  <Modal
    :show="show"
    title="Quyền xem mật khẩu theo công cụ"
    max-width="max-w-2xl"
    @close="emit('close')"
  >
    <p class="mb-4 text-sm text-slate-600">
      Chọn <strong>công cụ / tài khoản AI</strong>, sau đó thêm thành viên chỉ được xem mật khẩu
      <em>của công cụ đó</em> khi mở form Sửa. Quản trị viên vẫn xem được mọi mật khẩu.
    </p>

    <label
      for="pwd-viewer-ai-account"
      class="mb-1 block text-xs font-medium text-slate-500"
    >
      Công cụ / tài khoản AI
    </label>
    <select
      id="pwd-viewer-ai-account"
      v-model="selectedId"
      class="input h-10 w-full text-sm"
      :disabled="!accountOptions.length"
    >
      <option
        v-if="!accountOptions.length"
        :value="null"
      >
        Chưa có tài khoản AI
      </option>
      <option
        v-for="opt in accountOptions"
        :key="opt.id"
        :value="opt.id"
      >
        {{ opt.label }}
      </option>
    </select>
    <p
      v-if="selectedAccount"
      class="mt-1 text-xs text-slate-500"
    >
      Đang cấp quyền cho: <span class="font-medium text-slate-700">{{ selectedLabel }}</span>
    </p>

    <div
      v-if="selectedId"
      class="mt-4"
    >
      <PasswordViewerAccountPick
        :candidates="candidates"
        :disabled="loading || saving || !candidates.length"
        @pick="onPick"
      />
      <p
        v-if="!loading && !candidates.length"
        class="mt-1 text-xs text-slate-500"
      >
        Đã thêm tất cả thành viên khả dụng cho công cụ này.
      </p>
    </div>

    <div
      v-if="selectedId"
      class="mt-5 border-t border-slate-100 pt-4"
    >
      <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
        Được xem MK của công cụ này ({{ viewers.length }})
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
        Chưa có thành viên nào (ngoài quản trị viên) cho công cụ này.
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
