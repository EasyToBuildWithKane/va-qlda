<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import EmployeeAutocomplete from './EmployeeAutocomplete.vue';
import { useToast } from '@/shared/composables/useToast';
import { fileKindLabel } from '@/modules/contract/composables/useContractFormat.js';

const INPUT_CLASS = 'input h-10 w-full text-sm';

const props = defineProps({
    show: { type: Boolean, default: false },
    contract: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    billingOptions: { type: Array, default: () => [] },
    nextContractCode: { type: String, default: '' },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const isEdit = computed(() => !!props.contract?.id);

const displayCode = computed(() => {
    if (isEdit.value) return props.contract?.code ?? '';
    return props.nextContractCode || 'HD-00-0000';
});

const form = useForm({
    name: '',
    vendor_id: null,
    category_id: null,
    using_unit: '',
    owner_id: null,
    manager_id: null,
    billing_cycle: null,
    effective_date: '',
    expiry_date: '',
    status: 'active',
    description: '',
});

const attachMode = ref('file');
const linkDraft = ref('');
const pendingItems = ref([]);
const fileInputRef = ref(null);

const MAX_FILES_PER_UPLOAD = 10;
const MAX_PENDING_ATTACHMENTS = 30;

const existingAttachments = computed(() => props.contract?.attachments ?? []);

const pendingFileCount = computed(() => pendingItems.value.filter((i) => i.kind === 'file').length);
const pendingLinkCount = computed(() => pendingItems.value.filter((i) => i.kind === 'link').length);

const modalDirty = computed(() => form.isDirty || pendingItems.value.length > 0);

const serviceGroupOptions = computed(() => {
    const byName = new Map();
    for (const c of props.categories) {
        if (!byName.has(c.name)) byName.set(c.name, c);
    }
    return [...byName.values()].sort((a, b) => {
        const order = (a.sort_order ?? 0) - (b.sort_order ?? 0);
        if (order !== 0) return order;
        return String(a.name).localeCompare(String(b.name), 'vi');
    });
});

function resetPending() {
    pendingItems.value = [];
    linkDraft.value = '';
    attachMode.value = 'file';
}

watch(() => props.show, (open) => {
    if (!open) return;
    resetPending();
    const c = props.contract;
    form.clearErrors();
    form.defaults({
        name: c?.name ?? '',
        vendor_id: c?.vendor_id ?? null,
        category_id: c?.category_id ?? null,
        using_unit: c?.using_unit ?? '',
        owner_id: c?.owner?.id ?? null,
        manager_id: c?.manager?.id ?? null,
        billing_cycle: c?.billing_cycle?.value ?? null,
        effective_date: c?.effective_date ?? '',
        expiry_date: c?.expiry_date ?? '',
        status: c?.status?.value ?? 'draft',
        description: c?.description ?? '',
    });
    form.reset();
});

function pickFiles() {
    fileInputRef.value?.click();
}

function pushPendingLink(raw) {
    const part = String(raw ?? '').trim();
    if (!part) return false;
    const dup = pendingItems.value.some((i) => i.kind === 'link' && i.url === part);
    if (dup) return false;
    const name = part.includes('/') ? part.replace(/\\/g, '/').split('/').pop() : part;
    pendingItems.value.push({
        key: `link-${Date.now()}-${Math.random()}`,
        kind: 'link',
        name: name || part,
        typeLabel: fileKindLabel(part),
        url: part,
    });
    return true;
}

function onFilesSelected(e) {
    const list = Array.from(e.target.files || []);
    let added = 0;
    for (const file of list) {
        if (pendingItems.value.length >= MAX_PENDING_ATTACHMENTS) {
            toast.error(`Tối đa ${MAX_PENDING_ATTACHMENTS} hồ sơ chờ lưu mỗi lần.`);
            break;
        }
        const dup = pendingItems.value.some(
            (i) => i.kind === 'file' && i.name === file.name && i.file?.size === file.size,
        );
        if (dup) continue;
        pendingItems.value.push({
            key: `${Date.now()}-${file.name}-${Math.random()}`,
            kind: 'file',
            name: file.name,
            typeLabel: fileKindLabel(file.name, file.type),
            file,
        });
        added += 1;
    }
    e.target.value = '';
    if (added > 1) {
        toast.success(`Đã thêm ${added} file vào danh sách.`);
    }
}

function addLinkDraft() {
    const raw = linkDraft.value.trim();
    if (!raw) {
        toast.error('Hãy nhập ít nhất một link hoặc tên file.');
        return;
    }
    const parts = raw.split(/[\n\r;,]+/).map((s) => s.trim()).filter(Boolean);
    let added = 0;
    for (const part of parts) {
        if (pendingItems.value.length >= MAX_PENDING_ATTACHMENTS) {
            toast.error(`Tối đa ${MAX_PENDING_ATTACHMENTS} hồ sơ chờ lưu mỗi lần.`);
            break;
        }
        if (pushPendingLink(part)) added += 1;
    }
    linkDraft.value = '';
    if (added === 0 && parts.length) {
        toast.error('Các link trùng đã có trong danh sách.');
    } else if (added > 1) {
        toast.success(`Đã thêm ${added} link.`);
    }
}

function removePending(key) {
    pendingItems.value = pendingItems.value.filter((i) => i.key !== key);
}

function postLinkAttachment(contractId, url) {
    return new Promise((resolve, reject) => {
        router.post(`/contracts/${contractId}/attachments`, {
            category: 'contract',
            external_url: url,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('link')),
        });
    });
}

function postFileAttachments(contractId, files) {
    if (!files.length) return Promise.resolve();
    const chunks = [];
    for (let i = 0; i < files.length; i += MAX_FILES_PER_UPLOAD) {
        chunks.push(files.slice(i, i + MAX_FILES_PER_UPLOAD));
    }
    return chunks.reduce(
        (chain, chunk) => chain.then(() => new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('category', 'contract');
            chunk.forEach((f, j) => fd.append(`files[${j}]`, f));
            router.post(`/contracts/${contractId}/attachments`, fd, {
                preserveScroll: true,
                preserveState: true,
                forceFormData: true,
                onSuccess: () => resolve(),
                onError: () => reject(new Error('file')),
            });
        })),
        Promise.resolve(),
    );
}

async function uploadPending(contractId) {
    const files = pendingItems.value.filter((i) => i.kind === 'file').map((i) => i.file);

    if (isEdit.value) {
        const links = pendingItems.value.filter((i) => i.kind === 'link');
        for (const item of links) {
            await postLinkAttachment(contractId, item.url);
        }
    }
    await postFileAttachments(contractId, files);
}

function buildSubmitPayload(data) {
    const linkUrls = pendingItems.value
        .filter((i) => i.kind === 'link')
        .map((i) => i.url);
    const payload = { ...data };
    if (isEdit.value) {
        return payload;
    }
    delete payload.status;
    return { ...payload, links: linkUrls };
}

function submit() {
    form.transform(buildSubmitPayload);

    const opts = {
        preserveScroll: true,
        onSuccess: async (page) => {
            const id = isEdit.value
                ? props.contract.id
                : (page.props.flash?.created_contract_id ?? null);
            const hasFiles = pendingItems.value.some((i) => i.kind === 'file');
            const hasEditLinks = isEdit.value && pendingItems.value.some((i) => i.kind === 'link');
            if (id && (hasFiles || hasEditLinks)) {
                try {
                    await uploadPending(id);
                } catch {
                    toast.error('Hợp đồng đã lưu nhưng một số hồ sơ chưa tải lên được.');
                }
            }
            resetPending();
            emit('saved');
            emit('close');
        },
    };

    if (isEdit.value) {
        form.put(`/contracts/${props.contract.id}`, opts);
    } else {
        form.post('/contracts', opts);
    }
}
</script>

<template>
  <Modal
    :show="show"
    :title="isEdit ? 'Sửa hợp đồng' : 'Tạo hợp đồng'"
    max-width="max-w-6xl"
    :dirty="modalDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-6"
      @submit.prevent="submit"
    >
      <!-- Hàng 1: mã + dịch vụ + NCC -->
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-code-preview"
            label="Mã HĐ"
            wide
            tooltip="Mã hệ thống tự sinh theo dạng HD-YY-XXXX. Không chỉnh tay — dùng khi tra cứu và nhập Excel."
          />
          <input
            id="contract-code-preview"
            :value="displayCode"
            type="text"
            disabled
            class="input h-10 w-full cursor-not-allowed bg-slate-100 font-mono text-sm text-slate-600"
            aria-readonly="true"
          >
        </div>
        <div class="min-w-0 xl:col-span-1">
          <VendorFieldLabel
            for-id="contract-name"
            label="Tên DV"
            required
            wide
            tooltip="Tên dịch vụ / sản phẩm theo hợp đồng (vd: Kidsonline). Trường bắt buộc duy nhất."
          />
          <input
            id="contract-name"
            v-model="form.name"
            :class="INPUT_CLASS"
            placeholder="VD: Kidsonline"
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.name }}
          </p>
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-vendor"
            label="Tên NCC"
            tooltip="Nhà cung cấp ký hợp đồng — chọn từ danh mục đã có."
          />
          <select
            id="contract-vendor"
            v-model="form.vendor_id"
            :class="INPUT_CLASS"
          >
            <option :value="null">
              Chưa chọn NCC
            </option>
            <option
              v-for="v in vendors"
              :key="v.id"
              :value="v.id"
            >
              {{ v.name }}
            </option>
          </select>
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-category"
            label="Nhóm DV"
            tooltip="Nhóm dịch vụ (Giáo vụ số, License…) — phân loại trên explorer và báo cáo."
          />
          <select
            id="contract-category"
            v-model="form.category_id"
            :class="INPUT_CLASS"
          >
            <option :value="null">
              Chưa chọn nhóm
            </option>
            <option
              v-for="c in serviceGroupOptions"
              :key="c.id"
              :value="c.id"
            >
              {{ c.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Hàng 2: phòng ban + nhân sự -->
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="min-w-0 xl:col-span-2">
          <VendorFieldLabel
            for-id="contract-using-unit"
            label="Phòng ban"
            tooltip="Đơn vị sử dụng dịch vụ (vd: Mầm non Bình Thới)."
          />
          <input
            id="contract-using-unit"
            v-model="form.using_unit"
            :class="INPUT_CLASS"
            placeholder="VD: Mầm non Bình Thới"
          >
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            label="Người phụ trách"
            tooltip="Email hoặc tên nhân sự VA — khớp hồ sơ employee."
          />
          <EmployeeAutocomplete
            v-model="form.owner_id"
            :options="employees"
            placeholder="Email hoặc tên…"
          />
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            label="Người quản lý"
            tooltip="Quản lý phê duyệt / giám sát hợp đồng."
          />
          <EmployeeAutocomplete
            v-model="form.manager_id"
            :options="employees"
            placeholder="Email hoặc tên…"
          />
        </div>
      </div>

      <!-- Hàng 3: thời hạn + chu kỳ + trạng thái -->
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-effective"
            label="Ngày bắt đầu"
            tooltip="Ngày hiệu lực hợp đồng."
          />
          <input
            id="contract-effective"
            v-model="form.effective_date"
            type="date"
            :class="INPUT_CLASS"
          >
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-expiry"
            label="Ngày hết hạn"
            tooltip="Ngày kết thúc — dùng cảnh báo gia hạn."
          />
          <input
            id="contract-expiry"
            v-model="form.expiry_date"
            type="date"
            :class="INPUT_CLASS"
          >
          <p
            v-if="form.errors.expiry_date"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.expiry_date }}
          </p>
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-billing"
            label="Chu kỳ"
            tooltip="Chu kỳ thanh toán: hàng năm, tháng, một lần…"
          />
          <select
            id="contract-billing"
            v-model="form.billing_cycle"
            :class="INPUT_CLASS"
          >
            <option :value="null">
              Chưa chọn
            </option>
            <option
              v-for="o in billingOptions"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div class="min-w-0">
          <VendorFieldLabel
            for-id="contract-status"
            label="Trạng thái"
            tooltip="Trạng thái vòng đời: đang hiệu lực, chuyển phụ lục, hết hạn…"
          />
          <template v-if="isEdit">
            <select
              id="contract-status"
              v-model="form.status"
              :class="INPUT_CLASS"
            >
              <option
                v-for="o in statusOptions"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </template>
          <template v-else>
            <div class="flex h-10 items-center rounded-lg bg-slate-100 px-3 text-sm text-slate-500">
              Đang chờ duyệt
            </div>
            <p class="mt-1 text-[10px] text-slate-400">
              Hợp đồng mới luôn bắt đầu ở trạng thái này.
            </p>
          </template>
        </div>
      </div>

      <div>
        <VendorFieldLabel
          for-id="contract-notes"
          label="Ghi chú"
          wide
          tooltip="Ghi chú nội bộ (vd: HCQT mua) — không thay thế file hợp đồng."
        />
        <textarea
          id="contract-notes"
          v-model="form.description"
          rows="2"
          class="input w-full text-sm"
          placeholder="VD: HCQT mua"
        />
      </div>

      <!-- Hồ sơ đính kèm -->
      <section class="rounded-xl border border-slate-200/80 bg-slate-50/40 p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <h3 class="text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
              Link &amp; file hợp đồng
            </h3>
            <p class="mt-0.5 text-[11px] text-slate-500">
              Chọn nhiều file và thêm nhiều link — có thể lặp lại trước khi lưu.
            </p>
            <p
              v-if="pendingFileCount || pendingLinkCount"
              class="mt-1 text-[11px] font-medium text-brand"
            >
              Đang chờ:
              <span v-if="pendingFileCount">{{ pendingFileCount }} file</span>
              <span v-if="pendingFileCount && pendingLinkCount"> · </span>
              <span v-if="pendingLinkCount">{{ pendingLinkCount }} link</span>
            </p>
          </div>
          <div class="flex gap-1 rounded-lg bg-slate-200/60 p-1 text-sm">
            <button
              type="button"
              class="rounded-md px-3 py-1.5 font-medium transition-colors"
              :class="attachMode === 'file' ? 'bg-white text-brand shadow-sm' : 'text-slate-600'"
              @click="attachMode = 'file'"
            >
              Tải file
            </button>
            <button
              type="button"
              class="rounded-md px-3 py-1.5 font-medium transition-colors"
              :class="attachMode === 'link' ? 'bg-white text-brand shadow-sm' : 'text-slate-600'"
              @click="attachMode = 'link'"
            >
              Link / tên file
            </button>
          </div>
        </div>

        <div
          v-if="attachMode === 'file'"
          class="flex flex-wrap items-center gap-3"
        >
          <button
            type="button"
            class="btn-ghost h-10 gap-1.5"
            @click="pickFiles"
          >
            <AppIcon
              name="upload"
              :size="16"
            />
            Chọn file (nhiều file)
          </button>
          <span class="text-xs text-slate-500">
            Chọn lại để thêm tiếp · PDF, Word, Excel… tối đa 20MB/file · {{ MAX_FILES_PER_UPLOAD }} file/lượt upload
          </span>
          <input
            ref="fileInputRef"
            type="file"
            class="hidden"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.txt"
            @change="onFilesSelected"
          >
        </div>
        <div
          v-else
          class="space-y-2"
        >
          <textarea
            v-model="linkDraft"
            rows="3"
            class="input w-full font-mono text-sm"
            placeholder="Mỗi dòng một link hoặc tên file&#10;KidsOnline.pdf&#10;https://..."
            @keydown.ctrl.enter.prevent="addLinkDraft"
          />
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="btn-ghost h-10 shrink-0"
              @click="addLinkDraft"
            >
              Thêm vào danh sách
            </button>
            <span class="text-xs text-slate-500">
              Dán nhiều dòng cùng lúc (Ctrl+Enter để thêm nhanh)
            </span>
          </div>
        </div>

        <ul
          v-if="existingAttachments.length && isEdit"
          class="mt-4 space-y-2 border-t border-slate-200/80 pt-3"
        >
          <li class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Đã lưu trên hệ thống
          </li>
          <li
            v-for="a in existingAttachments"
            :key="a.id"
            class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
          >
            <span class="shrink-0 rounded-md bg-brand/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-brand">
              {{ a.is_external ? 'Link' : fileKindLabel(a.original_name, a.mime_type) }}
            </span>
            <span class="min-w-0 flex-1 truncate font-medium text-slate-800">{{ a.original_name }}</span>
          </li>
        </ul>

        <ul
          v-if="pendingItems.length"
          class="mt-4 space-y-2"
          :class="{ 'border-t border-slate-200/80 pt-3': isEdit && existingAttachments.length }"
        >
          <li
            v-if="pendingItems.length"
            class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"
          >
            Chờ lưu cùng hợp đồng
          </li>
          <li
            v-for="item in pendingItems"
            :key="item.key"
            class="flex items-center gap-3 rounded-lg border border-dashed border-brand/30 bg-white px-3 py-2 text-sm"
          >
            <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600">
              {{ item.typeLabel }}
            </span>
            <span class="min-w-0 flex-1 truncate font-medium text-slate-800">{{ item.name }}</span>
            <button
              type="button"
              class="shrink-0 rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
              aria-label="Xoá khỏi danh sách"
              @click="removePending(item.key)"
            >
              <AppIcon
                name="close"
                :size="16"
              />
            </button>
          </li>
        </ul>

        <p
          v-if="!pendingItems.length && !(isEdit && existingAttachments.length)"
          class="mt-3 text-center text-xs text-slate-400"
        >
          Chưa có hồ sơ — có thể bổ sung sau khi lưu.
        </p>
      </section>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
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
          {{ form.processing ? 'Đang lưu…' : (isEdit ? 'Lưu thay đổi' : 'Tạo hợp đồng') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
