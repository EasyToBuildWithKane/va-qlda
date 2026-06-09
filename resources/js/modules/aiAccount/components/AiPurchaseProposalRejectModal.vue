<script setup>
import { reactive, ref, watch, inject } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, entityRevisionFrom } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: Boolean,
    proposal: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);
const modalClose = inject('modalClose', () => emit('close'));

const dirty = ref(false);
const form = reactive({ rejection_reason: '' });

const formDraft = useModalFormDraft('ai-proposal-reject', {
    getScope: () => props.proposal?.id ?? 'none',
    fields: ['rejection_reason'],
});

const saveDraftOnClose = () => {
    formDraft.saveOnClose({ rejection_reason: form.rejection_reason }, buildDraftSaveMeta(props.proposal));
};

watch(() => props.show, async (open) => {
    if (!open) return;
    dirty.value = false;
    form.rejection_reason = '';
    const epoch = formDraft.bumpOpenEpoch();
    await formDraft.tryRestore((data) => {
        form.rejection_reason = data.rejection_reason ?? '';
        if (form.rejection_reason.trim()) dirty.value = true;
    }, {
        isActive: () => props.show,
        openEpoch: epoch,
        entityRevision: entityRevisionFrom(props.proposal),
    });
});

function onInput() {
    dirty.value = true;
}

function handleSubmit() {
    formDraft.clear();
    emit('submit', { rejection_reason: form.rejection_reason.trim() });
}
</script>

<template>
  <Modal
    :show="show"
    title="Từ chối đề xuất"
    max-width="max-w-lg"
    :dirty="dirty"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <form
      class="space-y-4"
      @submit.prevent="handleSubmit"
    >
      <p
        v-if="proposal"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
      >
        <span class="font-semibold">{{ proposal.tool_name }}</span>
        · {{ proposal.group_function }}
      </p>

      <div>
        <label class="label flex items-center gap-1">
          Lý do từ chối <span class="text-danger">*</span>
          <FieldTooltip
            wide
            text="Ghi rõ lý do để người đề xuất biết và có thể chỉnh sửa, gửi lại sau."
          />
        </label>
        <textarea
          v-model="form.rejection_reason"
          rows="4"
          required
          minlength="10"
          maxlength="2000"
          class="input w-full"
          placeholder="VD: Ngân sách quý này đã đầy; vui lòng ưu tiên gia hạn license hiện có…"
          @input="onInput"
        />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="btn-secondary"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary bg-rose-600 hover:bg-rose-700"
        >
          Xác nhận từ chối
        </button>
      </div>
    </form>
  </Modal>
</template>
