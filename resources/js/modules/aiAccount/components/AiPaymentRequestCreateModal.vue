<script setup>
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import { PROPOSAL_FORM_HINTS as H } from '@/modules/aiAccount/config/proposalFormHints';
import {
    registrationEmailsFromRow,
    staffSlotsFromCount,
    syncRegistrationEmailSlots,
} from '@/modules/aiAccount/utils/registrationEmailSlots';

const props = defineProps({
    show: Boolean,
    proposal: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const registrationEmails = ref([]);

const staffCount = computed(() =>
    staffSlotsFromCount(props.proposal?.staff_count ?? props.proposal?.quantity ?? 1),
);

watch(
    () => [props.show, props.proposal],
    ([open]) => {
        if (!open || !props.proposal) return;
        registrationEmails.value = registrationEmailsFromRow(props.proposal, staffCount.value);
    },
    { immediate: true },
);

watch(staffCount, (n) => {
    registrationEmails.value = syncRegistrationEmailSlots(registrationEmails.value, n);
});

function handleSubmit() {
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
          @click="emit('close')"
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
