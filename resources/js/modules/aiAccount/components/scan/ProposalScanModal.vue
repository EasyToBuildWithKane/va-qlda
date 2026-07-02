<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { EMPTY_LABELS, displayOrEmpty } from '@/shared/utils/emptyDisplay';
import {
    SCAN_FIELD_CONFIG,
    confidenceTone,
    useProposalScan,
} from '@/modules/aiAccount/composables/useProposalScan';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);

const {
    uploading,
    saving,
    scan,
    fields,
    signatures,
    uploadFile,
    saveAndConfirm,
    reset,
} = useProposalScan();

const fileInput = ref(null);
const cameraInput = ref(null);
const dragOver = ref(false);
const form = reactive({});

const step = computed(() => {
    if (scan.value && scan.value.status?.value !== 'failed') return 'review';
    return 'upload';
});

const dirty = computed(() => step.value === 'review' && scan.value?.status?.value !== 'confirmed');

const isImage = computed(() => (scan.value?.mime_type ?? '').startsWith('image/'));
const isPdf = computed(() => scan.value?.mime_type === 'application/pdf');

const confirmed = computed(() => scan.value?.status?.value === 'confirmed');

watch(scan, (value) => {
    if (!value) return;
    for (const field of SCAN_FIELD_CONFIG) {
        form[field.key] = value.fields?.[field.key]?.value ?? '';
    }
});

watch(() => props.show, (open) => {
    if (open) {
        reset();
        for (const field of SCAN_FIELD_CONFIG) form[field.key] = '';
    }
});

function fieldConfidence(key) {
    const raw = fields.value?.[key]?.confidence;
    return typeof raw === 'number' ? raw : null;
}

function confidenceBadgeClass(confidence) {
    const tone = confidenceTone(confidence);
    return {
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        amber: 'bg-amber-50 text-amber-700 ring-amber-200',
        rose: 'bg-rose-50 text-rose-700 ring-rose-200',
    }[tone];
}

function inputClass(key) {
    const confidence = fieldConfidence(key);
    const needsReview = confidence !== null && confidence < 0.7;
    return [
        'input w-full text-sm',
        needsReview ? 'border-amber-300 bg-amber-50/40' : '',
    ];
}

function onFilePicked(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (file) uploadFile(file);
}

function onDrop(event) {
    dragOver.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) uploadFile(file);
}

async function onConfirm() {
    const result = await saveAndConfirm({ ...form });
    if (result?.proposal_id) {
        emit('saved', result);
    }
}

function close() {
    emit('close');
}
</script>

<template>
  <Modal
    :show="show"
    title="Quét Phiếu Đề Xuất (OCR)"
    max-width="max-w-5xl"
    :dirty="dirty"
    close-confirm-title="Huỷ quét phiếu?"
    close-confirm-message="Dữ liệu đã trích xuất chưa được lưu thành Phiếu Đề Xuất."
    @close="close"
  >
    <!-- Bước 1: upload / chụp ảnh -->
    <div
      v-if="step === 'upload'"
      class="space-y-4"
    >
      <p class="text-sm text-slate-500">
        Tải lên hoặc chụp ảnh Phiếu Đề Xuất giấy (PDF, JPG, PNG · tối đa 10MB).
        Hệ thống tự động xoay ảnh, khử nhiễu và trích xuất nội dung, chữ ký bằng AI.
      </p>

      <div
        class="flex min-h-[220px] flex-col items-center justify-center gap-3 rounded-card border-2 border-dashed px-6 py-10 text-center transition"
        :class="dragOver ? 'border-brand bg-brand/5' : 'border-slate-200 bg-slate-50/60'"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
      >
        <template v-if="uploading">
          <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-brand border-t-transparent" />
          <p class="text-sm font-medium text-slate-700">
            Đang xử lý OCR… (thường dưới 5 giây)
          </p>
          <p class="text-xs text-slate-400">
            Tiền xử lý ảnh · nhận diện văn bản tiếng Việt · tách vùng chữ ký
          </p>
        </template>
        <template v-else>
          <span class="grid h-12 w-12 place-items-center rounded-full bg-brand/10 text-brand">
            <AppIcon
              name="upload"
              :size="22"
            />
          </span>
          <p class="text-sm text-slate-600">
            Kéo thả file vào đây, hoặc
          </p>
          <div class="flex flex-wrap items-center justify-center gap-2">
            <button
              type="button"
              class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
              @click="fileInput?.click()"
            >
              <AppIcon
                name="documents"
                :size="15"
              />
              Chọn file
            </button>
            <button
              type="button"
              class="btn-ghost inline-flex h-9 items-center gap-1.5 border border-slate-200 px-3 text-xs font-medium"
              @click="cameraInput?.click()"
            >
              <AppIcon
                name="image"
                :size="15"
              />
              Chụp ảnh
            </button>
          </div>
          <p
            v-if="scan?.status?.value === 'failed'"
            class="text-xs font-medium text-rose-600"
          >
            {{ scan.error_message || 'Xử lý thất bại. Vui lòng thử lại với file khác.' }}
          </p>
        </template>

        <input
          ref="fileInput"
          type="file"
          accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
          class="hidden"
          @change="onFilePicked"
        >
        <input
          ref="cameraInput"
          type="file"
          accept="image/*"
          capture="environment"
          class="hidden"
          @change="onFilePicked"
        >
      </div>
    </div>

    <!-- Bước 2: review / chỉnh sửa -->
    <div
      v-else
      class="space-y-4"
    >
      <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">
          <AppIcon
            name="documents"
            :size="13"
          />
          {{ scan.original_name }}
        </span>
        <span
          v-if="scan.duration_ms"
          class="rounded-full bg-slate-100 px-2.5 py-1"
        >Xử lý {{ (scan.duration_ms / 1000).toFixed(1) }}s · {{ scan.pages }} trang</span>
        <span
          class="rounded-full px-2.5 py-1 font-medium ring-1"
          :class="confirmed ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'"
        >{{ scan.status.label }}</span>
      </div>

      <div class="grid gap-5 lg:grid-cols-2">
        <!-- Preview bản gốc -->
        <div class="min-h-[320px] overflow-hidden rounded-card border border-slate-200 bg-slate-50">
          <img
            v-if="isImage && scan.file_url"
            :src="scan.file_url"
            alt="Bản gốc phiếu đề xuất"
            class="h-full max-h-[560px] w-full object-contain"
          >
          <iframe
            v-else-if="isPdf && scan.file_url"
            :src="scan.file_url"
            title="Bản gốc phiếu đề xuất (PDF)"
            class="h-[560px] w-full"
          />
          <p
            v-else
            class="p-6 text-center text-sm text-slate-400"
          >
            Không hiển thị được bản gốc.
          </p>
        </div>

        <!-- Form dữ liệu trích xuất -->
        <div class="space-y-3">
          <p class="text-xs text-slate-500">
            Kiểm tra và chỉnh sửa dữ liệu OCR trước khi lưu. Trường viền vàng có độ tin cậy thấp — cần đối chiếu bản gốc.
          </p>

          <div
            v-for="field in SCAN_FIELD_CONFIG"
            :key="field.key"
            class="space-y-1"
          >
            <div class="flex items-center justify-between gap-2">
              <label
                :for="`scan-field-${field.key}`"
                class="text-xs font-medium text-slate-600"
              >
                {{ field.label }}<span
                  v-if="field.required"
                  class="text-rose-500"
                > *</span>
              </label>
              <span
                v-if="fieldConfidence(field.key) !== null"
                class="rounded-full px-2 py-0.5 text-[10px] font-semibold tabular-nums ring-1"
                :class="confidenceBadgeClass(fieldConfidence(field.key))"
                :title="`Độ tin cậy OCR: ${Math.round(fieldConfidence(field.key) * 100)}%`"
              >{{ Math.round(fieldConfidence(field.key) * 100) }}%</span>
              <span
                v-else
                class="text-[10px] text-slate-400"
              >{{ EMPTY_LABELS.notUpdated }}</span>
            </div>
            <textarea
              v-if="field.type === 'textarea'"
              :id="`scan-field-${field.key}`"
              v-model="form[field.key]"
              rows="3"
              :class="inputClass(field.key)"
              :disabled="confirmed"
            />
            <input
              v-else
              :id="`scan-field-${field.key}`"
              v-model="form[field.key]"
              :type="field.type === 'number' ? 'number' : 'text'"
              :class="inputClass(field.key)"
              :disabled="confirmed"
            >
          </div>
        </div>
      </div>

      <!-- Chữ ký nhận diện -->
      <div class="rounded-card border border-slate-200 p-4">
        <h3 class="mb-3 text-sm font-semibold text-slate-700">
          Chữ ký nhận diện
        </h3>
        <p
          v-if="signatures.length === 0"
          class="text-sm text-slate-400"
        >
          Không phát hiện vùng chữ ký nào trên phiếu.
        </p>
        <div
          v-else
          class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
        >
          <div
            v-for="sig in signatures"
            :key="sig.id"
            class="space-y-2 rounded-card border border-slate-100 bg-slate-50/60 p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="truncate text-xs font-semibold text-slate-700">
                {{ sig.role.label }}
              </p>
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-semibold tabular-nums ring-1"
                :class="confidenceBadgeClass(sig.confidence)"
              >{{ Math.round(sig.confidence * 100) }}%</span>
            </div>
            <div class="grid h-16 place-items-center overflow-hidden rounded-btn bg-white ring-1 ring-slate-200">
              <img
                v-if="sig.image_url"
                :src="sig.image_url"
                :alt="`Chữ ký ${sig.role.label}`"
                class="max-h-16 w-full object-contain"
              >
              <span
                v-else
                class="text-[11px] text-slate-400"
              >Không có ảnh</span>
            </div>
            <p class="text-[11px] text-slate-500">
              <span
                :class="sig.signed ? 'text-emerald-600' : 'text-rose-500'"
                class="font-medium"
              >{{ sig.signed ? 'Đã ký' : 'Chưa ký' }}</span>
              · {{ displayOrEmpty(sig.signer_name, EMPTY_LABELS.notUpdated) }}
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9 border border-slate-200 px-3 text-xs font-medium"
          @click="close"
        >
          {{ confirmed ? 'Đóng' : 'Huỷ' }}
        </button>
        <button
          v-if="!confirmed"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-xs font-semibold disabled:opacity-60"
          :disabled="saving"
          @click="onConfirm"
        >
          <span
            v-if="saving"
            class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
          />
          <AppIcon
            v-else
            name="check"
            :size="15"
          />
          Lưu thành Phiếu Đề Xuất
        </button>
      </div>
    </div>
  </Modal>
</template>
