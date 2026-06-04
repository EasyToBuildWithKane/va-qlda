<script setup>
/* eslint-disable vue/no-v-html -- server-rendered proposal preview HTML from authenticated API */
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import ProposerEmployeePick from '@/modules/aiAccount/components/ProposerEmployeePick.vue';
import { useProposalPdfPreview } from '@/modules/aiAccount/composables/useProposalPdfPreview';
import MoneyInput from '@/shared/ui/MoneyInput.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import { PROPOSAL_FORM_HINTS as H, PROPOSAL_FORM_PLACEHOLDERS as P } from '@/modules/aiAccount/config/proposalFormHints';

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
            vendors: [],
            send_to: [],
            account_templates: [],
        }),
    },
});

const emit = defineEmits(['close', 'submit']);

const SEND_TO_DEFAULT = 'Ban Giám đốc\nPhòng Công nghệ & Phòng Kế Toán';

const TABS = [
    { key: 'proposer', label: 'Người đề xuất', icon: 'account' },
    { key: 'tool', label: 'Công cụ & chi phí', icon: 'money' },
    { key: 'content', label: 'Nội dung phiếu', icon: 'edit' },
    { key: 'more', label: 'Tùy chỉnh', icon: 'settings' },
    { key: 'preview', label: 'Xem trước', icon: 'pdf' },
];

const dirty = ref(false);
const activeTab = ref('proposer');
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

const roleTitleSuggestions = computed(() => {
    const set = new Set();
    for (const e of props.formLookups.employees ?? []) {
        if (e.role_title?.trim()) set.add(e.role_title.trim());
    }
    return [...set].sort((a, b) => a.localeCompare(b, 'vi'));
});

const emailSuggestions = computed(() => {
    const set = new Set();
    for (const e of props.formLookups.employees ?? []) {
        if (e.email?.trim()) set.add(e.email.trim());
    }
    for (const t of props.formLookups.account_templates ?? []) {
        if (t.registration_email?.trim()) set.add(t.registration_email.trim());
    }
    return [...set].sort();
});

const phoneSuggestions = computed(() => {
    const set = new Set();
    for (const e of props.formLookups.employees ?? []) {
        if (e.phone?.trim()) set.add(e.phone.trim());
    }
    return [...set];
});

const sendToPresets = computed(() => {
    const list = props.formLookups.send_to ?? [];
    const seen = new Set();
    const out = [];
    for (const raw of [SEND_TO_DEFAULT, ...list]) {
        const v = String(raw ?? '').trim();
        if (!v || seen.has(v)) continue;
        seen.add(v);
        out.push(v);
    }
    return out;
});

const licenseSuggestions = computed(() => {
    const fromConfig = props.options.license_types ?? [];
    const fromTpl = (props.formLookups.account_templates ?? []).map((t) => t.license_type).filter(Boolean);
    return [...new Set([...fromConfig, ...fromTpl])];
});

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

const tabIndex = computed(() => TABS.findIndex((t) => t.key === activeTab.value));

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
    if (form.subject_about || form.send_to !== SEND_TO_DEFAULT) {
        activeTab.value = 'more';
    }
}

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    activeTab.value = 'proposer';
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

function applySendToPreset(text) {
    form.send_to = text;
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

const previewSection = computed(() => (activeTab.value === 'preview' ? 'preview' : 'form'));

const {
    html: previewHtml,
    loading: previewLoading,
    error: previewError,
    reset: resetPreview,
} = useProposalPdfPreview(form, previewSection, buildSubmitPayload);

function goTab(key) {
    activeTab.value = key;
}

function goAdjacent(delta) {
    const next = Math.min(TABS.length - 1, Math.max(0, tabIndex.value + delta));
    activeTab.value = TABS[next].key;
}

function handleSubmit() {
    if (!costPreviewAmount.value) {
        activeTab.value = 'tool';
        return;
    }
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
    max-width="max-w-6xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <p class="mb-3 text-sm text-slate-600">
      Gõ để gợi ý từ danh mục nhân sự, công cụ AI và phiếu trước. Trích yếu &amp; người tiếp nhận tự điền khi gửi.
    </p>

    <form
      class="flex min-h-[min(78vh,760px)] flex-col"
      @submit.prevent="handleSubmit"
    >
      <div
        class="mb-4 flex flex-wrap gap-1 border-b border-slate-200 pb-2"
        role="tablist"
      >
        <button
          v-for="tab in TABS"
          :key="tab.key"
          type="button"
          role="tab"
          class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="activeTab === tab.key
            ? 'bg-brand text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100'"
          :aria-selected="activeTab === tab.key"
          @click="goTab(tab.key)"
        >
          <AppIcon
            :name="tab.icon"
            :size="14"
          />
          {{ tab.label }}
        </button>
      </div>

      <div class="min-h-[min(52vh,520px)] flex-1 overflow-y-auto px-0.5">
        <!-- Tab: Người đề xuất -->
        <div
          v-show="activeTab === 'proposer'"
          class="grid grid-cols-1 gap-5 lg:grid-cols-2"
        >
          <div class="lg:col-span-2">
            <ProposalFormLabel
              label="Tìm nhân sự"
              :tooltip="H.proposer_pick"
            />
            <ProposerEmployeePick
              v-model="selectedProposerId"
              :employees="formLookups.employees"
              :initial-label="form.proposer_name"
              @pick="onProposerPicked"
            />
          </div>
          <div>
            <ProposalFormLabel
              label="Họ & tên"
              required
              :tooltip="H.proposer_name"
            />
            <input
              v-model="form.proposer_name"
              type="text"
              required
              class="input w-full"
              :placeholder="P.proposer_name"
              autocomplete="name"
              @input="onInput"
            >
          </div>
          <div>
            <ProposalFormLabel
              label="Chức vụ"
              :tooltip="H.proposer_position"
            />
            <input
              v-model="form.proposer_position"
              type="text"
              list="proposal-role-titles"
              class="input w-full"
              :placeholder="P.proposer_position"
              autocomplete="organization-title"
              @input="onInput"
            >
            <datalist id="proposal-role-titles">
              <option
                v-for="r in roleTitleSuggestions"
                :key="r"
                :value="r"
              />
            </datalist>
          </div>
          <div>
            <ProposalFormLabel
              label="Phòng ban"
              :tooltip="H.proposer_department"
            />
            <input
              v-model="form.proposer_department"
              type="text"
              list="proposal-departments"
              class="input w-full"
              :placeholder="P.proposer_department"
              autocomplete="organization"
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
          <div>
            <ProposalFormLabel
              label="Email (tiếp nhận trên phiếu)"
              :tooltip="H.recipient_email"
            />
            <input
              v-model="form.proposer_email"
              type="email"
              list="proposal-emails"
              class="input w-full"
              :placeholder="P.proposer_email"
              autocomplete="email"
              @input="onInput"
            >
            <datalist id="proposal-emails">
              <option
                v-for="em in emailSuggestions"
                :key="em"
                :value="em"
              />
            </datalist>
          </div>
          <div>
            <ProposalFormLabel
              label="Điện thoại"
              :tooltip="H.recipient_phone"
            />
            <input
              v-model="form.proposer_phone"
              type="tel"
              list="proposal-phones"
              class="input w-full"
              :placeholder="P.proposer_phone"
              autocomplete="tel"
              @input="onInput"
            >
            <datalist id="proposal-phones">
              <option
                v-for="ph in phoneSuggestions"
                :key="ph"
                :value="ph"
              />
            </datalist>
          </div>
        </div>

        <!-- Tab: Công cụ & chi phí -->
        <div
          v-show="activeTab === 'tool'"
          class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
          <div class="sm:col-span-2 lg:col-span-3">
            <ProposalFormLabel
              label="Công cụ / sản phẩm"
              required
              :tooltip="H.tool_name"
            />
            <input
              v-model="form.tool_name"
              type="text"
              required
              list="proposal-tools"
              class="input w-full"
              :placeholder="P.tool_name"
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
            <ProposalFormLabel
              label="Nhóm chức năng"
              required
              :tooltip="H.group_function"
            />
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
            <ProposalFormLabel
              label="Gói / license"
              required
              :tooltip="H.license_type"
            />
            <input
              v-model="form.license_type"
              type="text"
              required
              list="proposal-license-types"
              class="input w-full"
              :placeholder="P.license_type"
              @input="onInput"
            >
            <datalist id="proposal-license-types">
              <option
                v-for="t in licenseSuggestions"
                :key="t"
                :value="t"
              />
            </datalist>
          </div>
          <div>
            <ProposalFormLabel
              label="Chi phí (VNĐ)"
              required
              :tooltip="H.cost_amount"
            />
            <MoneyInput
              v-model="form.cost_amount"
              :placeholder="P.cost_amount"
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
                />/tháng
              </span>
            </p>
          </div>
          <div>
            <ProposalFormLabel
              label="Chu kỳ thanh toán"
              :tooltip="H.cost_unit"
            />
            <select
              v-model="form.cost_unit"
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
            <ProposalFormLabel
              label="Số nhân sự"
              :tooltip="H.staff_count"
            />
            <input
              v-model="form.staff_count"
              type="number"
              min="1"
              class="input w-full"
              :placeholder="P.staff_count"
              @input="onInput"
            >
          </div>
          <div>
            <ProposalFormLabel
              label="Mua mới / Gia hạn"
              :tooltip="H.purchase_type"
            />
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
            <ProposalFormLabel
              label="Ngày đưa vào sử dụng"
              :tooltip="H.planned_use_date"
            />
            <input
              v-model="form.planned_use_date"
              type="date"
              class="input w-full"
              @input="onInput"
            >
          </div>
          <div class="sm:col-span-2">
            <ProposalFormLabel
              label="Email đăng ký tài khoản"
              :tooltip="H.registration_email"
            />
            <input
              v-model="form.registration_email"
              type="email"
              list="proposal-reg-emails"
              class="input w-full"
              :placeholder="P.registration_email"
              @input="onInput"
            >
            <datalist id="proposal-reg-emails">
              <option
                v-for="em in emailSuggestions"
                :key="`reg-${em}`"
                :value="em"
              />
            </datalist>
          </div>
        </div>

        <!-- Tab: Nội dung -->
        <div v-show="activeTab === 'content'">
          <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
              <ProposalFormLabel
                label="Nội dung đề xuất"
                required
                :tooltip="H.proposal_content"
                hint="Tối thiểu 20 ký tự"
              />
              <textarea
                v-model="form.proposal_content"
                rows="6"
                required
                minlength="20"
                class="input w-full"
                :placeholder="P.proposal_content"
                @input="onInput"
              />
            </div>
            <div class="lg:col-span-2">
              <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <ProposalFormLabel
                    label="Mục tiêu (tùy chọn)"
                    :tooltip="H.objectives"
                  />
                </div>
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
                rows="5"
                class="input w-full text-sm"
                :placeholder="P.objectives"
                @input="onInput"
              />
            </div>
          </div>
        </div>

        <!-- Tab: Tùy chỉnh -->
        <div
          v-show="activeTab === 'more'"
          class="max-w-3xl space-y-4"
        >
          <p class="text-xs text-slate-500">
            Trích yếu mặc định:
            <span class="font-medium text-slate-700">{{ derivedSubject || '—' }}</span>
          </p>
          <div>
            <ProposalFormLabel
              label="Trích yếu (ghi đè)"
              :tooltip="H.subject_about"
            />
            <input
              v-model="form.subject_about"
              type="text"
              class="input w-full"
              :placeholder="P.subject_about"
              @input="onInput"
            >
          </div>
          <div>
            <ProposalFormLabel
              label="Kính gửi"
              :tooltip="H.send_to"
            />
            <div
              v-if="sendToPresets.length"
              class="mb-2 flex flex-wrap gap-1.5"
            >
              <button
                v-for="(preset, idx) in sendToPresets"
                :key="idx"
                type="button"
                class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-600 hover:border-brand/40 hover:text-brand"
                @click="applySendToPreset(preset)"
              >
                {{ preset.split('\n')[0] }}{{ preset.includes('\n') ? '…' : '' }}
              </button>
            </div>
            <textarea
              v-model="form.send_to"
              rows="4"
              class="input w-full font-mono text-sm leading-relaxed"
              :placeholder="P.send_to"
              @input="onInput"
            />
          </div>
        </div>

        <!-- Tab: Xem trước -->
        <div v-show="activeTab === 'preview'">
          <p
            v-if="previewError"
            class="mb-2 text-sm text-rose-600"
          >
            {{ previewError }}
          </p>
          <p
            v-if="previewLoading"
            class="text-sm text-slate-500"
          >
            Đang tải bản xem trước…
          </p>
          <div
            v-else-if="previewHtml"
            class="proposal-pdf-preview max-h-[min(68vh,640px)] min-h-[min(40vh,360px)] overflow-auto rounded-lg border border-slate-200 bg-white p-3"
            v-html="previewHtml"
          />
          <p
            v-else
            class="text-sm text-slate-500"
          >
            Điền các tab trước rồi quay lại đây để xem phiếu in.
          </p>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4">
        <div class="flex gap-2">
          <button
            type="button"
            class="btn-ghost text-sm"
            :disabled="tabIndex <= 0"
            @click="goAdjacent(-1)"
          >
            Quay lại
          </button>
          <button
            v-if="activeTab !== 'preview'"
            type="button"
            class="btn-secondary text-sm"
            @click="goAdjacent(1)"
          >
            Tiếp theo
          </button>
        </div>
        <div class="flex gap-2">
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
            {{ isEditing ? 'Lưu' : 'Gửi phiếu' }}
          </button>
        </div>
      </div>
    </form>
  </Modal>
</template>
