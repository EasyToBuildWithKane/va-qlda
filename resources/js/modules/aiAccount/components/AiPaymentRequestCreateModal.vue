<script setup>
import { computed, inject, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import { PROPOSAL_FORM_HINTS as H } from '@/modules/aiAccount/config/proposalFormHints';
import {
    registrationEmailsFromRow,
    staffSlotsFromCount,
    syncRegistrationEmailSlots,
} from '@/modules/aiAccount/utils/registrationEmailSlots';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: Boolean,
    proposal: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);
const modalClose = inject('modalClose', () => emit('close'));

const registrationEmails = ref([]);
const dirty = ref(false);

const staffCount = computed(() =>
    staffSlotsFromCount(props.proposal?.staff_count ?? props.proposal?.quantity ?? 1),
);

const formDraft = useModalFormDraft('ai-payment-request', {
    getScope: () => props.proposal?.id ?? 'none',
    pick: () => ({ emails: [...registrationEmails.value] }),
    hasContent: (data) => (data.emails ?? []).some((e) => (e ?? '').trim().length > 0),
});

const saveDraftOnClose = () => {
    formDraft.saveOnClose({}, buildDraftSaveMeta(props.proposal));
};

watch(
    () => [props.show, props.proposal?.id],
    async ([open]) => {
        if (!open || !props.proposal) return;
        dirty.value = false;
        registrationEmails.value = registrationEmailsFromRow(props.proposal, staffCount.value);
        const epoch = formDraft.bumpOpenEpoch();
        const restored = await formDraft.tryRestore((data) => {
            if (Array.isArray(data.emails)) {
                registrationEmails.value = syncRegistrationEmailSlots(data.emails, staffCount.value);
            }
        }, {
            isActive: () => props.show,
            openEpoch: epoch,
            entityRevision: props.proposal?.updated_at ?? null,
        });
        if (restored) dirty.value = true;
    },
);

watch(staffCount, (n) => {
    registrationEmails.value = syncRegistrationEmailSlots(registrationEmails.value, n);
});

watch(registrationEmails, () => {
    if (props.show) dirty.value = true;
}, { deep: true });

function handleSubmit() {
    formDraft.clear();
    emit('submit', {
        registration_emails: registrationEmails.value.map((e) => (e ?? '').trim()),
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Tạo đề nghị thanh toán"
    max-width="max-w-lg"
    :dirty="dirty"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <div
      v-if="proposal"
      class="space-y-4"
    >
      <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm">
        <p class="font-mono text-xs text-slate-500">
          {{ proposal.proposal_code }}
        </p>
        <p class="mt-1 font-semibold text-slate-800">
          {{ proposal.tool_name }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
          Số nhân sự sử dụng: <span class="font-medium text-slate-700">{{ staffCount }}</span>
        </p>
      </div>

      <div class="space-y-3">
        <p class="text-xs text-slate-500">
          Email đăng ký tài khoản (tùy chọn, khớp số nhân sự) — hiển thị trên phiếu ĐNTT và khi lập TK AI.
        </p>
        <div
          v-for="(_, idx) in registrationEmails"
          :key="idx"
          class="space-y-1"
        >
          <ProposalFormLabel
            :label="`Email đăng ký ${idx + 1}`"
            :tooltip="H.registration_email"
          />
          <input
            v-model="registrationEmails[idx]"
            type="email"
            class="input w-full"
            :placeholder="`Nhân sự ${idx + 1} — để trống nếu chưa có`"
          >
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button
          type="button"
          class="btn btn-ghost h-9"
          :disabled="loading"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn btn-primary h-9"
          :disabled="loading"
          @click="handleSubmit"
        >
          <span v-if="loading">Đang tạo…</span>
          <span v-else>Tạo ĐNTT</span>
        </button>
      </div>
    </div>
  </Modal>
</template>
