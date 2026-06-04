<script setup>
/* eslint-disable vue/no-v-html -- server-rendered proposal preview HTML from authenticated API */
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import ProposerEmployeePick from '@/modules/aiAccount/components/ProposerEmployeePick.vue';
import { useProposalPdfPreview } from '@/modules/aiAccount/composables/useProposalPdfPreview';
import MoneyInput from '@/shared/ui/MoneyInput.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    show: Boolean,
    editProposal: { type: Object, default: () => null },
    options: { type: Object, required: true },
    proposalDefaults: { type: Object, default: () => ({}) },
    formLookups: {
        type: Object,
        default: () => ({
            employees: [],
            departments: [],
            tools: [],
            account_templates: [],
        }),
    },
});

const emit = defineEmits(['close', 'submit']);

const SEND_TO_DEFAULT = 'Ban Giám đốc\nPhòng Công nghệ & Phòng Kế Toán';

const dirty = ref(false);
const showPreview = ref(false);
const showAdvanced = ref(false);
const selectedProposerId = ref(null);

const form = reactive({
    proposer_name: '',
    proposer_position: '',
    proposer_department: '',
    proposer_email: '',
    proposer_phone: '',
    tool_name: '',
    group_function: 'DEV',
    license_type: 'Pro',
    cost_amount: '',
    cost_unit: 'monthly',
    purchase_type: 'new',
    staff_count: '1',
    planned_use_date: '',
    registration_email: '',
    proposal_content: '',
    objectives: '',
    subject_about: '',
    send_to: '',
});

const departmentNames = computed(() =>
    (props.formLookups.departments ?? []).map((d) => d.name).filter(Boolean),
);

const costPreviewAmount = computed(() => {
    const n = parseInt(String(form.cost_amount).replace(/\D/g, ''), 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
});

const monthlyCost = computed(() => {
    const n = costPreviewAmount.value;
    if (!n) return 0;
    if (form.cost_unit === 'yearly') return Math.round(n / 12);
    if (form.cost_unit === 'quarterly') return Math.round(n / 3);
    if (form.cost_unit === 'one_time') return 0;
    return n;
});

const derivedSubject = computed(() => {
    const custom = form.subject_about.trim();
    if (custom) return custom;
    const tool = form.tool_name.trim();
    return tool ? `Đăng ký sử dụng ${tool}` : '';
});

const isEditing = computed(() => Boolean(props.editProposal?.id));

const modalTitle = computed(() =>
    (isEditing.value ? 'Chỉnh sửa phiếu đề xuất' : 'Phiếu đề xuất mua AI'));

function defaultForm() {
    const d = props.proposalDefaults ?? {};
    return {
        proposer_name: d.proposer_name ?? '',
        proposer_position: d.proposer_position ?? '',
        proposer_department: d.proposer_department ?? '',
        proposer_email: d.recipient_email ?? '',
        proposer_phone: d.recipient_phone ?? '',
        tool_name: '',
        group_function: 'DEV',
        license_type: 'Pro',
        cost_amount: '',
        cost_unit: 'monthly',
        purchase_type: 'new',
        staff_count: '1',
        planned_use_date: '',
        registration_email: '',
        proposal_content: '',
        objectives: d.objectives ?? '',
        subject_about: '',
        send_to: d.send_to ?? SEND_TO_DEFAULT,
    };
}

function populateFromProposal(row) {
    const tool = row.tool_name ?? '';
    const autoSubject = tool ? `Đăng ký sử dụng ${tool}` : '';
    const subject = row.subject_about ?? '';
    Object.assign(form, {
        proposer_name: row.proposer_name ?? '',
        proposer_position: row.proposer_position ?? '',
        proposer_department: row.proposer_department ?? '',
        proposer_email: row.recipient_email ?? '',
        proposer_phone: row.recipient_phone ?? '',
        tool_name: tool,
        group_function: row.group_function ?? 'DEV',
        license_type: row.license_type ?? 'Pro',
        cost_amount: row.cost_amount != null ? String(row.cost_amount) : '',
        cost_unit: row.cost_unit ?? 'monthly',
        purchase_type: row.purchase_type ?? 'new',
        staff_count: String(row.staff_count ?? row.seats ?? row.quantity ?? 1),
        planned_use_date: row.planned_use_date ?? '',
        registration_email: row.registration_email ?? '',
        proposal_content: row.proposal_content ?? row.justification ?? '',
        objectives: row.objectives ?? '',
        subject_about: subject !== autoSubject ? subject : '',
        send_to: row.send_to ?? SEND_TO_DEFAULT,
    });
    showAdvanced.value = Boolean(form.subject_about) || form.send_to !== SEND_TO_DEFAULT;
}

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    showPreview.value = false;
    showAdvanced.value = false;
    resetPreview();
    selectedProposerId.value = props.proposalDefaults?.proposer_employee_id ?? null;
    if (props.editProposal?.id) {
        populateFromProposal(props.editProposal);
    } else {
        Object.assign(form, defaultForm());
    }
});

function onProposerPicked(emp) {
    if (!emp) return;
    form.proposer_name = emp.name ?? '';
    form.proposer_position = emp.role_title ?? '';
    if (emp.department) form.proposer_department = emp.department;
    if (emp.email) form.proposer_email = emp.email;
    if (emp.phone) form.proposer_phone = emp.phone;
    onInput();
}

function applyToolTemplate() {
    const name = form.tool_name?.trim();
    if (!name) return;
    const tpl = props.formLookups.account_templates?.find(
        (t) => t.tool_name?.toLowerCase() === name.toLowerCase(),
    );
    if (!tpl) return;
    form.license_type = tpl.license_type ?? form.license_type;
    form.group_function = tpl.group_function ?? form.group_function;
    if (!form.cost_amount && tpl.cost_amount) form.cost_amount = String(tpl.cost_amount);
    if (tpl.cost_unit) form.cost_unit = tpl.cost_unit;
    if (!form.registration_email && tpl.registration_email) {
        form.registration_email = tpl.registration_email;
    }
    onInput();
}

function fillSampleObjectives() {
    const sample = props.proposalDefaults?.objectives;
    if (sample) form.objectives = sample;
    onInput();
}

function onInput() {
    dirty.value = true;
}

function buildSubmitPayload() {
    const staff = form.staff_count ? parseInt(form.staff_count, 10) : 1;
    const tool = form.tool_name.trim();
    const proposer = form.proposer_name.trim();

    return {
        proposal_type: 'ai_account',
        subject_about: derivedSubject.value,
        send_to: form.send_to.trim() || SEND_TO_DEFAULT,
        tool_name: tool,
        group_function: form.group_function,
        license_type: form.license_type.trim(),
        cost_amount: parseInt(String(form.cost_amount).replace(/\D/g, ''), 10) || 0,
        cost_unit: form.cost_unit,
        quantity: staff,
        seats: staff,
        staff_count: staff,
        purchase_type: form.purchase_type,
        proposer_name: proposer,
        proposer_position: form.proposer_position.trim() || undefined,
        proposer_department: form.proposer_department.trim() || undefined,
        proposal_content: form.proposal_content.trim(),
        objectives: form.objectives.trim() || undefined,
        department_using: form.proposer_department.trim() || undefined,
        recipient_name: proposer || undefined,
        recipient_position: form.proposer_position.trim() || undefined,
        recipient_email: form.proposer_email.trim() || undefined,
        recipient_phone: form.proposer_phone.trim() || undefined,
        registration_email: form.registration_email.trim() || undefined,
        planned_use_date: form.planned_use_date || undefined,
    };
}

const previewSection = computed(() => (showPreview.value ? 'preview' : 'form'));

const {
    html: previewHtml,
    loading: previewLoading,
    error: previewError,
    reset: resetPreview,
} = useProposalPdfPreview(form, previewSection, buildSubmitPayload);

function handleSubmit() {
    if (!costPreviewAmount.value) return;
    emit('submit', {
        id: props.editProposal?.id ?? null,
        ...buildSubmitPayload(),
    });
}
</script>

<template>
  <Modal
    :show="show"
    :title="modalTitle"
    max-width="max-w-3xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <p class="mb-4 text-sm text-slate-600">
      Chỉ nhập thông tin cần in trên phiếu PDX. Trích yếu, kính gửi và người tiếp nhận được điền tự động từ các ô bên dưới.
    </p>

    <form @submit.prevent="handleSubmit">
      <fieldset class="mb-6 space-y-4 rounded-xl border border-slate-100 bg-slate-50/50 p-4">
        <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Người đề xuất
        </legend>
        <ProposerEmployeePick
          v-model="selectedProposerId"
          :employees="formLookups.employees"
          :initial-label="form.proposer_name"
          @pick="onProposerPicked"
        />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div>
            <label class="label">Họ &amp; tên <span class="text-danger">*</span></label>
            <input
              v-model="form.proposer_name"
              type="text"
              required
              class="input w-full"
              @input="onInput"
            >
          </div>
          <div>
            <label class="label">Chức vụ</label>
            <input
              v-model="form.proposer_position"
              type="text"
              class="input w-full"
              @input="onInput"
            >
          </div>
          <div>
            <label class="label">Phòng ban</label>
            <input
              v-model="form.proposer_department"
              type="text"
              list="proposal-departments"
              class="input w-full"
              @input="onInput"
            >
            <datalist id="proposal-departments">
              <option
                v-for="name in departmentNames"
                :key="name"
                :value="name"
              />
            </datalist>
          </div>
        </div>
      </fieldset>

      <fieldset class="mb-6 space-y-4 rounded-xl border border-slate-100 p-4">
        <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Công cụ &amp; chi phí (mục 4 trên phiếu)
        </legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label">Công cụ / sản phẩm <span class="text-danger">*</span></label>
            <input
              v-model="form.tool_name"
              type="text"
              required
              list="proposal-tools"
              class="input w-full"
              placeholder="VD: Cursor Pro, Claude Team"
              @input="onInput"
              @blur="applyToolTemplate"
            >
            <datalist id="proposal-tools">
              <option
                v-for="t in formLookups.tools"
                :key="t"
                :value="t"
              />
            </datalist>
          </div>
          <div>
            <label class="label">Nhóm chức năng <span class="text-danger">*</span></label>
            <select
              v-model="form.group_function"
              required
              class="input w-full"
              @change="onInput"
            >
              <option
                v-for="o in options.group_function"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Gói / license <span class="text-danger">*</span></label>
            <input
              v-model="form.license_type"
              type="text"
              required
              list="proposal-license-types"
              class="input w-full"
              @input="onInput"
            >
            <datalist id="proposal-license-types">
              <option
                v-for="t in options.license_types"
                :key="t"
                :value="t"
              />
            </datalist>
          </div>
          <div>
            <label class="label">Chi phí (VNĐ) <span class="text-danger">*</span></label>
            <MoneyInput
              v-model="form.cost_amount"
              @update:model-value="onInput"
            />
            <p
              v-if="costPreviewAmount"
              class="mt-1 text-xs text-slate-600"
            >
              <VndAmount
                :amount="costPreviewAmount"
                inline
              />
              <span
                v-if="monthlyCost && form.cost_unit !== 'monthly' && form.cost_unit !== 'one_time'"
                class="text-slate-400"
              >
                · ~<VndAmount
                  :amount="monthlyCost"
                  inline
                />/tháng trên phiếu
              </span>
            </p>
          </div>
          <div>
            <label class="label">Chu kỳ <span class="text-danger">*</span></label>
            <select
              v-model="form.cost_unit"
              required
              class="input w-full"
              @change="onInput"
            >
              <option
                v-for="o in options.cost_unit"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="label flex items-center gap-1">
              Số nhân sự
              <FieldTooltip text="Dùng cho số lượng trên bảng ngân sách và mục 4.2 trên phiếu." />
            </label>
            <input
              v-model="form.staff_count"
              type="number"
              min="1"
              class="input w-full"
              @input="onInput"
            >
          </div>
          <div>
            <label class="label">Mua mới / Gia hạn</label>
            <select
              v-model="form.purchase_type"
              class="input w-full"
              @change="onInput"
            >
              <option
                v-for="o in options.purchase_type"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Ngày đưa vào sử dụng</label>
            <input
              v-model="form.planned_use_date"
              type="date"
              class="input w-full"
              @input="onInput"
            >
          </div>
          <div>
            <label class="label">Email đăng ký tài khoản</label>
            <input
              v-model="form.registration_email"
              type="email"
              class="input w-full"
              placeholder="Tùy chọn — in ở mục 4.5 nếu có"
              @input="onInput"
            >
          </div>
        </div>
      </fieldset>

      <fieldset class="mb-4 space-y-3 rounded-xl border border-slate-100 p-4">
        <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Nội dung in trên phiếu (mục 2–3)
        </legend>
        <div>
          <label class="label">Nội dung đề xuất <span class="text-danger">*</span></label>
          <textarea
            v-model="form.proposal_content"
            rows="4"
            required
            minlength="20"
            class="input w-full"
            placeholder="Team nào dùng, trong bao lâu, lý do chính…"
            @input="onInput"
          />
        </div>
        <div>
          <div class="mb-1 flex items-center justify-between gap-2">
            <label class="label mb-0">Mục tiêu (tùy chọn)</label>
            <button
              type="button"
              class="text-xs font-medium text-brand hover:underline"
              @click="fillSampleObjectives"
            >
              Dùng mẫu
            </button>
          </div>
          <textarea
            v-model="form.objectives"
            rows="3"
            class="input w-full text-sm"
            placeholder="Mỗi dòng một ý — bỏ trống nếu không cần mục 3"
            @input="onInput"
          />
        </div>
      </fieldset>

      <details
        class="mb-4 rounded-lg border border-slate-100 bg-white"
        :open="showAdvanced"
        @toggle="showAdvanced = $event.target.open"
      >
        <summary class="cursor-pointer px-4 py-2.5 text-sm font-medium text-slate-700">
          Tùy chỉnh trích yếu &amp; kính gửi
        </summary>
        <div class="space-y-3 border-t border-slate-100 px-4 py-3">
          <p class="text-xs text-slate-500">
            Mặc định trích yếu:
            <span class="font-medium text-slate-700">{{ derivedSubject || '—' }}</span>
          </p>
          <div>
            <label class="label">Trích yếu khác (ghi đè)</label>
            <input
              v-model="form.subject_about"
              type="text"
              class="input w-full"
              placeholder="Để trống = dùng mặc định theo tên công cụ"
              @input="onInput"
            >
          </div>
          <div>
            <label class="label">Kính gửi</label>
            <textarea
              v-model="form.send_to"
              rows="2"
              class="input w-full text-sm"
              @input="onInput"
            />
          </div>
        </div>
      </details>

      <details
        class="mb-6 rounded-lg border border-slate-200"
        :open="showPreview"
        @toggle="showPreview = $event.target.open"
      >
        <summary class="cursor-pointer px-4 py-2.5 text-sm font-medium text-slate-700">
          Xem trước phiếu (PDF)
        </summary>
        <div class="border-t border-slate-100 px-4 py-3">
          <p
            v-if="previewError"
            class="mb-2 text-sm text-rose-600"
          >
            {{ previewError }}
          </p>
          <p
            v-if="previewLoading"
            class="text-xs text-slate-500"
          >
            Đang tải…
          </p>
          <div
            v-else-if="previewHtml"
            class="proposal-pdf-preview max-h-[50vh] overflow-auto rounded border border-slate-100 bg-white p-2"
            v-html="previewHtml"
          />
          <p
            v-else
            class="text-xs text-slate-500"
          >
            Mở mục này để xem bản in trước khi gửi.
          </p>
        </div>
      </details>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-secondary"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
        >
          <AppIcon
            name="send"
            :size="14"
            class="mr-1 inline"
          />
          {{ isEditing ? 'Lưu thay đổi' : 'Gửi phiếu' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
